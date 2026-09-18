<?php
/**
 * Payment finalization + confirmation email.
 *
 * order_finalize_payment() is safe to call from both stripe-webhook.php and
 * success.php: it locks the order row, flips pending -> paid exactly once, and
 * atomically claims the right to send the confirmation email.
 */
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/boxes.php';
require_once __DIR__ . '/orders.php';

/**
 * @return array|null  order (with items) if the caller should send the email;
 *                      null if it was already sent or nothing to do.
 * @throws RuntimeException on a hard failure.
 */
function order_finalize_payment(PDO $pdo, int $orderId, string $sessionId, ?int $amountCents = null): ?array
{
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT id, status, confirmation_email_sent
               FROM ' . DOLOS_TBL_ORDERS . ' WHERE id = ? FOR UPDATE'
        );
        $stmt->execute([$orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $pdo->rollBack();
            throw new RuntimeException('Order not found for finalize: ' . $orderId);
        }

        if ($row['status'] !== 'paid') {
            $overbook = order_overbook_reasons($pdo, $orderId);

            $pdo->prepare(
                'UPDATE ' . DOLOS_TBL_ORDERS . "
                    SET status = 'paid', stripe_session_id = ?, hold_expires_at = NULL,
                        updated_at = NOW()
                  WHERE id = ?"
            )->execute([$sessionId, $orderId]);

            if ($amountCents !== null && $amountCents >= 0) {
                $pdo->prepare(
                    'UPDATE ' . DOLOS_TBL_ORDERS . ' SET total_amount_cents = ? WHERE id = ?'
                )->execute([$amountCents, $orderId]);
            }

            if ($overbook !== []) {
                $reason = mb_substr(implode('; ', $overbook), 0, 255);
                $pdo->prepare(
                    'UPDATE ' . DOLOS_TBL_ORDERS . ' SET capacity_flag = 1, flag_reason = ? WHERE id = ?'
                )->execute([$reason, $orderId]);
                app_log('high', 'Payment', 'order overbooked (flagged)', [
                    'order_id' => $orderId, 'reason' => $reason,
                ]);
            }
        }

        if ((int) $row['confirmation_email_sent'] === 1) {
            $pdo->commit();
            app_log('high', 'Payment', 'finalize: email already claimed', ['order_id' => $orderId]);
            return null;
        }

        $pdo->prepare(
            'UPDATE ' . DOLOS_TBL_ORDERS . ' SET confirmation_email_sent = 1 WHERE id = ?'
        )->execute([$orderId]);

        $pdo->commit();
        app_log('high', 'Payment', 'finalize: paid + email claimed', ['order_id' => $orderId]);

        return order_get_with_items($pdo, $orderId);
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        app_log('high', 'Payment', 'finalize: exception', ['order_id' => $orderId, 'error' => $e->getMessage()]);
        throw new RuntimeException('Finalize failed: ' . $e->getMessage(), 0, $e);
    }
}

/** @return string[] reasons a paid order sits above a box cap (audit only). */
function order_overbook_reasons(PDO $pdo, int $orderId): array
{
    $others = box_taken_counts($pdo, $orderId);
    $boxes  = [];
    foreach (get_all_boxes($pdo) as $b) {
        $boxes[(int) $b['id']] = $b;
    }

    $stmt = $pdo->prepare(
        'SELECT box_id, SUM(quantity) AS qty FROM ' . DOLOS_TBL_ITEMS . ' WHERE order_id = ? GROUP BY box_id'
    );
    $stmt->execute([$orderId]);

    $reasons = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $box  = $boxes[(int) $r['box_id']] ?? null;
        $mine = (int) $r['qty'];
        $used = $others[(int) $r['box_id']] ?? 0;
        if ($box && $used + $mine > (int) $box['cap']) {
            $reasons[] = sprintf('%s over cap (%d/%d)', $box['code'], $used + $mine, (int) $box['cap']);
        }
    }
    return $reasons;
}

function payment_finalize_and_notify(PDO $pdo, int $orderId, string $sessionId, ?int $amountCents = null): bool
{
    try {
        $order = order_finalize_payment($pdo, $orderId, $sessionId, $amountCents);
        if ($order !== null) {
            send_order_confirmation_email($pdo, $order);
        }
        return true;
    } catch (Throwable $e) {
        app_log('high', 'Payment', 'finalize+notify failed', [
            'order_id' => $orderId, 'error' => $e->getMessage(),
        ]);
        return false;
    }
}

function send_order_confirmation_email(PDO $pdo, array $order): bool
{
    $to = trim((string) $order['email']);
    $fallbackTo = 'com@crosspointchurchsv.org';
    $usedFallback = false;
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $to = $fallbackTo;
        $usedFallback = true;
    }

    $eventTitle    = app_title($pdo);
    $eventDate     = dolos_setting($pdo, 'event_date', '');
    $eventLocation = dolos_setting($pdo, 'event_location', 'Crosspoint Church');

    $rowsHtml = '';
    foreach ($order['items'] as $it) {
        $rowsHtml .= sprintf(
            '<tr><td style="padding:6px 12px;border-bottom:1px solid #eee;">%s</td>'
            . '<td style="padding:6px 12px;border-bottom:1px solid #eee;text-align:center;">%d</td>'
            . '<td style="padding:6px 12px;border-bottom:1px solid #eee;text-align:right;">%s</td></tr>',
            e($it['box_name']),
            (int) $it['quantity'],
            e(money((int) $it['unit_price_cents'] * (int) $it['quantity']))
        );
    }
    if ($rowsHtml === '') {
        $rowsHtml = '<tr><td colspan="3" style="padding:6px 12px;color:#6b7280;">No lunch boxes — attendance only</td></tr>';
    }

    $phone = trim((string) ($order['phone'] ?? ''));
    $fallbackNote = $usedFallback
        ? '<p style="font-size:14px;color:#b45309;background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;padding:10px 12px;">'
          . 'No email was provided with this order. Confirmation sent to the church office.'
          . ($phone !== '' ? ' Phone on file: <strong>' . e($phone) . '</strong>.' : '')
          . '</p>'
        : '';

    $templatePath = dirname(__DIR__) . '/emails/order-confirmation.html';
    $template = is_file($templatePath) ? file_get_contents($templatePath) : '<p>Hi {{NAME}}, your order (#{{ORDER_ID}}) is confirmed.</p>{{ITEMS_TABLE}}<p>Total paid: {{TOTAL}}</p>';

    $body = strtr($template, [
        '{{NAME}}'           => e(trim($order['first_name'] . ' ' . $order['last_name'])),
        '{{ORDER_ID}}'       => (string) (int) $order['id'],
        '{{ITEMS_TABLE}}'    => $rowsHtml,
        '{{TOTAL}}'          => e(money((int) $order['total_amount_cents'])),
        '{{CAMPUS}}'         => e((string) ($order['campus'] ?? '')),
        '{{LIFT_GROUP}}'     => e((string) ($order['lift_group'] ?? '')),
        '{{ATTENDING_ADULTS}}'  => (string) (int) ($order['attending_adults'] ?? 0),
        '{{ATTENDING_CHILDREN}}'=> (string) (int) ($order['attending_children'] ?? 0),
        '{{ADULT_NAMES_LINE}}'  => ($an = decode_attendee_names($order['adult_names'] ?? null))
            ? '<br><span style="color:#6b7280;">' . e(implode(', ', $an)) . '</span>'
            : '',
        '{{CHILD_NAMES_LINE}}'  => ($cn = decode_attendee_names($order['child_names'] ?? null))
            ? '<br><span style="color:#6b7280;">' . e(implode(', ', $cn)) . '</span>'
            : '',
        '{{FALLBACK_NOTE}}'  => $fallbackNote,
        '{{EVENT_TITLE}}'    => e($eventTitle),
        '{{EVENT_DATE}}'     => e($eventDate),
        '{{EVENT_LOCATION}}' => e($eventLocation),
    ]);

    $subject = $eventTitle . ' — Order Confirmed (#' . (int) $order['id'] . ')';

    $replyTo    = email_list((string) env('reply_to', 'cd@crosspointchurchsv.org'));
    $ccList     = email_list((string) env('cc', ''));
    $returnPath = trim((string) env('return_path', ''));
    $fromEmail  = $replyTo[0] ?? 'cd@crosspointchurchsv.org';

    $headers = [
        'From: Crosspoint Church <' . $fromEmail . '>',
        'Reply-To: ' . ($replyTo ? implode(', ', $replyTo) : $fromEmail),
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion(),
    ];
    if ($returnPath !== '' && filter_var($returnPath, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Return-Path: ' . $returnPath;
    }
    if ($ccList) {
        $headers[] = 'Cc: ' . implode(', ', $ccList);
    }

    $params = ($returnPath !== '' && filter_var($returnPath, FILTER_VALIDATE_EMAIL))
        ? '-f ' . escapeshellarg($returnPath)
        : null;

    $sent = $params !== null
        ? mail($to, $subject, $body, implode("\r\n", $headers), $params)
        : mail($to, $subject, $body, implode("\r\n", $headers));

    app_log('high', 'Email', $sent ? 'confirmation sent' : 'confirmation FAILED', [
        'order_id' => $order['id'], 'to' => $to, 'fallback' => $usedFallback,
    ]);

    return $sent;
}
