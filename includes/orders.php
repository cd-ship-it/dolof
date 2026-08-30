<?php
/**
 * Order creation + lifecycle.
 *
 * The race-condition guard lives in create_pending_order(): every selected box
 * is locked with GET_LOCK (in a stable order to avoid deadlock), capacity is
 * re-checked against live paid + held quantities, and only then is the pending
 * order written. Locks are released after commit.
 */
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/boxes.php';

/**
 * @param array $customer  ['first_name','last_name','email','phone','campus','lift_group']
 * @param array $lines      each: ['box_id','code','name','unit_price_cents','quantity']
 * @return int  new order id
 * @throws BoxCapacityException when a box would exceed its cap
 * @throws RuntimeException     on lock failure / db error
 */
function create_pending_order(PDO $pdo, array $customer, array $lines, int $holdMinutes): int
{
    if ($lines === []) {
        throw new RuntimeException('No lunch boxes selected.');
    }

    // Lock every box, ordered by code, so concurrent requests queue deterministically.
    usort($lines, fn($a, $b) => strcmp($a['code'], $b['code']));
    $locks = [];

    try {
        foreach ($lines as $line) {
            $name = box_lock_name($line['code']);
            $got  = $pdo->query('SELECT GET_LOCK(' . $pdo->quote($name) . ', 10)')->fetchColumn();
            if ((int) $got !== 1) {
                throw new RuntimeException('The system is busy. Please try again in a moment.');
            }
            $locks[] = $name;
        }

        $pdo->beginTransaction();

        // Re-check capacity now that we hold the locks.
        $taken   = box_taken_counts($pdo);
        $boxById = [];
        foreach (get_active_boxes($pdo) as $b) {
            $boxById[(int) $b['id']] = $b;
        }

        $errors  = [];
        $soldOut = [];
        foreach ($lines as $line) {
            $box = $boxById[(int) $line['box_id']] ?? null;
            if ($box === null) {
                $errors[]  = 'A selected lunch box is no longer available.';
                $soldOut[] = $line['code'];
                continue;
            }
            $have = (int) $box['cap'] - ($taken[(int) $box['id']] ?? 0);
            if ((int) $line['quantity'] > $have) {
                $soldOut[] = $box['code'];
                $errors[]  = $have <= 0
                    ? sprintf('%s is sold out.', $box['name'])
                    : sprintf('Only %d left of %s.', $have, $box['name']);
            }
        }
        if ($errors !== []) {
            $pdo->rollBack();
            throw new BoxCapacityException($errors, array_values(array_unique($soldOut)));
        }

        $total = 0;
        foreach ($lines as $line) {
            $total += (int) $line['unit_price_cents'] * (int) $line['quantity'];
        }

        $pdo->prepare(
            'INSERT INTO ' . DOLOS_TBL_ORDERS . '
                (first_name, last_name, email, phone, campus, lift_group, status,
                 total_amount_cents, payment_method, hold_expires_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, \'pending\', ?, \'stripe\',
                     DATE_ADD(NOW(), INTERVAL ? MINUTE), NOW(), NOW())'
        )->execute([
            $customer['first_name'],
            $customer['last_name'],
            $customer['email'],
            $customer['phone'],
            $customer['campus'] ?? '',
            $customer['lift_group'] ?? '',
            $total,
            $holdMinutes,
        ]);
        $orderId = (int) $pdo->lastInsertId();

        $itemStmt = $pdo->prepare(
            'INSERT INTO ' . DOLOS_TBL_ITEMS . '
                (order_id, box_id, box_code, box_name, unit_price_cents, quantity)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($lines as $line) {
            $itemStmt->execute([
                $orderId,
                (int) $line['box_id'],
                $line['code'],
                $line['name'],
                (int) $line['unit_price_cents'],
                (int) $line['quantity'],
            ]);
        }

        $pdo->commit();

        app_log('high', 'Order', 'pending order created', [
            'order_id'    => $orderId,
            'email'       => $customer['email'],
            'total_cents' => $total,
            'hold_min'    => $holdMinutes,
        ]);

        return $orderId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } finally {
        foreach ($locks as $name) {
            $pdo->query('SELECT RELEASE_LOCK(' . $pdo->quote($name) . ')');
        }
    }
}

function order_get(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM ' . DOLOS_TBL_ORDERS . ' WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function order_get_with_items(PDO $pdo, int $id): ?array
{
    $order = order_get($pdo, $id);
    if ($order === null) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT * FROM ' . DOLOS_TBL_ITEMS . ' WHERE order_id = ? ORDER BY box_code'
    );
    $stmt->execute([$id]);
    $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $order;
}

function order_attach_stripe_session(PDO $pdo, int $id, string $sessionId): void
{
    $pdo->prepare(
        'UPDATE ' . DOLOS_TBL_ORDERS . ' SET stripe_session_id = ?, updated_at = NOW() WHERE id = ?'
    )->execute([$sessionId, $id]);
}

function mark_order_expired(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare(
        'UPDATE ' . DOLOS_TBL_ORDERS . "
            SET status = 'expired', hold_expires_at = NULL, updated_at = NOW()
          WHERE id = ? AND status = 'pending'"
    );
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

function mark_order_cancelled(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare(
        'UPDATE ' . DOLOS_TBL_ORDERS . "
            SET status = 'cancelled', hold_expires_at = NULL, updated_at = NOW()
          WHERE id = ? AND status = 'pending'"
    );
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

/** Pending orders whose hold has lapsed — candidates for reconcile/expire. */
function find_stale_pending_orders(PDO $pdo): array
{
    return $pdo->query(
        'SELECT id, stripe_session_id FROM ' . DOLOS_TBL_ORDERS . "
          WHERE status = 'pending'
            AND hold_expires_at IS NOT NULL
            AND hold_expires_at < NOW()
          ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC);
}
