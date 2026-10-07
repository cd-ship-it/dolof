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
 * @param array $customer  ['first_name','last_name','email','phone','campus','lift_group','attending_adults','attending_children','adult_attendees','child_attendees']
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

    require_once __DIR__ . '/helpers.php';

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

        $adultAttendees = array_values((array) ($customer['adult_attendees'] ?? []));
        $childAttendees = array_values((array) ($customer['child_attendees'] ?? []));

        $pdo->prepare(
            'INSERT INTO ' . DOLOS_TBL_ORDERS . '
                (first_name, last_name, email, phone, campus, lift_group,
                 attending_adults, attending_children, adult_names, child_names, status,
                 total_amount_cents, payment_method, hold_expires_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', ?, \'stripe\',
                     DATE_ADD(NOW(), INTERVAL ? MINUTE), NOW(), NOW())'
        )->execute([
            $customer['first_name'],
            $customer['last_name'],
            $customer['email'],
            $customer['phone'],
            $customer['campus'] ?? '',
            $customer['lift_group'] ?? '',
            (int) ($customer['attending_adults'] ?? 0),
            (int) ($customer['attending_children'] ?? 0),
            encode_attendees($adultAttendees),
            encode_attendees($childAttendees),
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

/**
 * Create a confirmed $0 RSVP (attendance only, no lunch boxes).
 *
 * @param array $customer same shape as create_pending_order
 * @return int new order id
 */
function create_rsvp_order(PDO $pdo, array $customer): int
{
    require_once __DIR__ . '/helpers.php';

    $adultAttendees = array_values((array) ($customer['adult_attendees'] ?? []));
    $childAttendees = array_values((array) ($customer['child_attendees'] ?? []));

    $pdo->prepare(
        'INSERT INTO ' . DOLOS_TBL_ORDERS . '
            (first_name, last_name, email, phone, campus, lift_group,
             attending_adults, attending_children, adult_names, child_names, status,
             total_amount_cents, payment_method, hold_expires_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'paid\', 0, \'rsvp\', NULL, NOW(), NOW())'
    )->execute([
        $customer['first_name'],
        $customer['last_name'],
        $customer['email'],
        $customer['phone'],
        $customer['campus'] ?? '',
        $customer['lift_group'] ?? '',
        (int) ($customer['attending_adults'] ?? 0),
        (int) ($customer['attending_children'] ?? 0),
        encode_attendees($adultAttendees),
        encode_attendees($childAttendees),
    ]);
    $orderId = (int) $pdo->lastInsertId();

    app_log('high', 'Order', 'rsvp order created', [
        'order_id' => $orderId,
        'email'    => $customer['email'],
    ]);

    return $orderId;
}

/**
 * Create a confirmed staff order — no payment, but otherwise no special
 * treatment: box capacity is locked and re-checked exactly like
 * create_pending_order(), so a staff order can sell out a box same as anyone
 * else's. Always recorded with total_amount_cents = 0 (payment_method =
 * 'staff'); item rows keep the box's real unit price so counts/values per
 * box stay accurate in the DB.
 *
 * Also atomically consumes the one-time staff token (locked and checked
 * inside the same transaction as the capacity check and the order insert),
 * so a token can never back more than one order even under concurrent
 * requests for the same link.
 *
 * @param array $customer same shape as create_pending_order
 * @param array $lines    each: ['box_id','code','name','unit_price_cents','quantity'] — may be empty
 * @return int new order id
 * @throws BoxCapacityException   when a selected box no longer has room
 * @throws StaffTokenUsedException when the token is invalid or already used
 * @throws RuntimeException       on lock failure / db error
 */
function create_staff_order(PDO $pdo, array $customer, array $lines, string $staffToken): int
{
    require_once __DIR__ . '/helpers.php';
    require_once __DIR__ . '/staff_tokens.php';

    $locks = [];
    if ($lines !== []) {
        usort($lines, fn($a, $b) => strcmp($a['code'], $b['code']));
        foreach ($lines as $line) {
            $name = box_lock_name($line['code']);
            $got  = $pdo->query('SELECT GET_LOCK(' . $pdo->quote($name) . ', 10)')->fetchColumn();
            if ((int) $got !== 1) {
                throw new RuntimeException('The system is busy. Please try again in a moment.');
            }
            $locks[] = $name;
        }
    }

    try {
        $pdo->beginTransaction();

        $tokenRow = staff_token_lock_unused($pdo, $staffToken);
        if ($tokenRow === null) {
            $pdo->rollBack();
            throw new StaffTokenUsedException();
        }

        if ($lines !== []) {
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
        }

        $adultAttendees = array_values((array) ($customer['adult_attendees'] ?? []));
        $childAttendees = array_values((array) ($customer['child_attendees'] ?? []));

        $pdo->prepare(
            'INSERT INTO ' . DOLOS_TBL_ORDERS . '
                (first_name, last_name, email, phone, campus, lift_group,
                 attending_adults, attending_children, adult_names, child_names, status,
                 total_amount_cents, payment_method, hold_expires_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'paid\', 0, \'staff\', NULL, NOW(), NOW())'
        )->execute([
            $customer['first_name'],
            $customer['last_name'],
            $customer['email'],
            $customer['phone'],
            $customer['campus'] ?? '',
            $customer['lift_group'] ?? '',
            (int) ($customer['attending_adults'] ?? 0),
            (int) ($customer['attending_children'] ?? 0),
            encode_attendees($adultAttendees),
            encode_attendees($childAttendees),
        ]);
        $orderId = (int) $pdo->lastInsertId();

        if ($lines !== []) {
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
        }

        staff_token_mark_used($pdo, (int) $tokenRow['id'], $orderId);

        $pdo->commit();
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

    app_log('high', 'Order', 'staff order created', [
        'order_id' => $orderId,
        'email'    => $customer['email'],
    ]);

    return $orderId;
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

/**
 * Mark a paid order as refunded. Lunch-box quota is released automatically
 * because box_taken_counts() only counts paid + held-pending orders.
 */
function mark_order_refunded(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare(
        'UPDATE ' . DOLOS_TBL_ORDERS . "
            SET status = 'refunded', hold_expires_at = NULL, updated_at = NOW()
          WHERE id = ? AND status = 'paid'"
    );
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

/**
 * Refund a paid order: Stripe charge (when applicable) then status → refunded.
 *
 * @return array{ok:bool, error?:string, refund_id?:string}
 */
function order_refund(PDO $pdo, int $id): array
{
    $order = order_get($pdo, $id);
    if ($order === null) {
        return ['ok' => false, 'error' => 'Order not found.'];
    }
    if (($order['status'] ?? '') !== 'paid') {
        return ['ok' => false, 'error' => 'Only paid orders can be refunded.'];
    }

    $paymentMethod = (string) ($order['payment_method'] ?? 'stripe');
    $sessionId     = trim((string) ($order['stripe_session_id'] ?? ''));
    $amountCents   = (int) ($order['total_amount_cents'] ?? 0);
    $refundId      = '';

    // Card charges go through Stripe Checkout; staff/rsvp / $0 skip Stripe.
    if ($paymentMethod === 'stripe' && $amountCents > 0) {
        if ($sessionId === '') {
            return ['ok' => false, 'error' => 'Missing Stripe session for this order.'];
        }
        if (STRIPE_SECRET_KEY === '') {
            return ['ok' => false, 'error' => 'Stripe is not configured.'];
        }

        try {
            \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
            $session = \Stripe\Checkout\Session::retrieve($sessionId);
            $pi = $session->payment_intent ?? null;
            if (is_object($pi) && isset($pi->id)) {
                $pi = $pi->id;
            }
            $pi = is_string($pi) ? trim($pi) : '';
            if ($pi === '') {
                return ['ok' => false, 'error' => 'No Stripe payment found for this order.'];
            }

            $refund = \Stripe\Refund::create([
                'payment_intent' => $pi,
                'metadata'       => [
                    'order_id' => (string) $id,
                    'source'   => 'dolos',
                ],
            ]);
            $refundId = (string) ($refund->id ?? '');
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // Already fully refunded in Stripe Dashboard — still free local quota.
            $msg = $e->getMessage();
            if (stripos($msg, 'already been refunded') === false
                && stripos($msg, 'has already been refunded') === false) {
                app_log('high', 'Refund', 'stripe refund failed', [
                    'order_id' => $id,
                    'error'    => $msg,
                ]);
                return ['ok' => false, 'error' => 'Stripe refund failed: ' . $msg];
            }
        } catch (Throwable $e) {
            app_log('high', 'Refund', 'stripe refund failed', [
                'order_id' => $id,
                'error'    => $e->getMessage(),
            ]);
            return ['ok' => false, 'error' => 'Stripe refund failed: ' . $e->getMessage()];
        }
    }

    if (!mark_order_refunded($pdo, $id)) {
        // Race: another admin may have refunded between the check and update.
        $fresh = order_get($pdo, $id);
        if ($fresh && ($fresh['status'] ?? '') === 'refunded') {
            return ['ok' => true, 'refund_id' => $refundId];
        }
        return ['ok' => false, 'error' => 'Could not update order status.'];
    }

    app_log('high', 'Refund', 'order refunded', [
        'order_id'  => $id,
        'refund_id' => $refundId,
        'method'    => $paymentMethod,
        'amount'    => $amountCents,
    ]);

    return ['ok' => true, 'refund_id' => $refundId];
}

/**
 * Unguessable cancel capability for Stripe cancel_url / pay-page cancel links.
 * Bound to the order id via HMAC so /cancel cannot mutate arbitrary pending orders.
 */
function order_cancel_token(int $orderId): string
{
    if ($orderId <= 0 || STRIPE_SECRET_KEY === '') {
        return '';
    }
    return hash_hmac('sha256', 'order-cancel:' . $orderId, STRIPE_SECRET_KEY);
}

function order_cancel_token_is_valid(int $orderId, string $token): bool
{
    $expected = order_cancel_token($orderId);
    return $expected !== '' && $token !== '' && hash_equals($expected, $token);
}

/** Absolute cancel URL with order id + cancel token query params. */
function order_cancel_url(int $orderId): string
{
    $token = order_cancel_token($orderId);
    return APP_URL . '/cancel?order=' . $orderId . '&token=' . rawurlencode($token);
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
