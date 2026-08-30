<?php
/**
 * Reconcile stale pending orders, then release the ones that never paid.
 *
 * For each pending order past its hold:
 *   - ask Stripe about the checkout session; if it is actually paid, finalize
 *     it (recovers a missed webhook) and send the confirmation email;
 *   - otherwise mark the order 'expired' so its seats stop counting.
 *
 * Cron (every 5 minutes):
 *   *\/5 * * * * php /path/to/dolof/scripts/cleanup-expired-orders.php >> /path/to/dolof/logs/cron.log 2>&1
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/logger.php';
require_once dirname(__DIR__) . '/includes/boxes.php';
require_once dirname(__DIR__) . '/includes/orders.php';
require_once dirname(__DIR__) . '/includes/mailer.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

$recovered = 0;
$expired   = 0;
$skipped   = 0;

foreach (find_stale_pending_orders($pdo) as $row) {
    $orderId   = (int) $row['id'];
    $sessionId = trim((string) ($row['stripe_session_id'] ?? ''));

    if ($sessionId !== '') {
        try {
            $session = \Stripe\Checkout\Session::retrieve($sessionId);
            if (($session->payment_status ?? '') === 'paid') {
                $amount = isset($session->amount_total) ? (int) $session->amount_total : null;
                if (payment_finalize_and_notify($pdo, $orderId, $sessionId, $amount)) {
                    $recovered++;
                    app_log('high', 'Cleanup', 'recovered paid order', ['order_id' => $orderId]);
                }
                continue;
            }
        } catch (Throwable $e) {
            app_log('high', 'Cleanup', 'stripe retrieve failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);
            $skipped++;
            continue;
        }
    }

    if (mark_order_expired($pdo, $orderId)) {
        $expired++;
        app_log('high', 'Cleanup', 'order expired', ['order_id' => $orderId]);
    }
}

$msg = "Cleanup complete: recovered={$recovered}, expired={$expired}, skipped={$skipped}";
app_log('high', 'Cleanup', 'run complete', compact('recovered', 'expired', 'skipped'));
if (PHP_SAPI === 'cli') {
    echo $msg . "\n";
}
