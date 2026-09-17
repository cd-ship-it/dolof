<?php
/**
 * Stripe webhook endpoint.
 *   checkout.session.completed / async_payment_succeeded -> finalize order + email
 *   checkout.session.expired / async_payment_failed     -> release hold / log failure
 *
 * Register at: <APP_URL>/stripe-webhook
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/boxes.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/vendor/autoload.php';

$payload = @file_get_contents('php://input');
$sig     = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (STRIPE_WEBHOOK_SECRET === '') {
    http_response_code(500);
    exit('Webhook secret not configured');
}

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig, STRIPE_WEBHOOK_SECRET);
} catch (Throwable $e) {
    app_log('high', 'Payment', 'webhook rejected (bad signature)', ['error' => $e->getMessage()]);
    http_response_code(400);
    exit('Invalid signature');
}

$type    = $event->type;
$object  = $event->data->object ?? null;
$orderId = 0;
if ($object && isset($object->metadata->order_id) && ($object->metadata->source ?? '') === 'dolos') {
    $orderId = (int) $object->metadata->order_id;
}

app_log('high', 'Payment', 'webhook received', [
    'type' => $type, 'order_id' => $orderId ?: null,
    'stripe_session_id' => $object->id ?? null,
]);

$finalizeTypes = [
    'checkout.session.completed',
    'checkout.session.async_payment_succeeded',
];

if (in_array($type, $finalizeTypes, true) && $orderId > 0) {
    $paymentStatus = (string) ($object->payment_status ?? '');
    // Delayed methods can fire completed while still unpaid — only fulfill when paid.
    if ($paymentStatus === 'unpaid') {
        app_log('high', 'Payment', 'webhook skip unpaid session', [
            'type' => $type, 'order_id' => $orderId, 'payment_status' => $paymentStatus,
        ]);
    } else {
        $amount = isset($object->amount_total) ? (int) $object->amount_total : null;
        if (!payment_finalize_and_notify($pdo, $orderId, $object->id, $amount)) {
            http_response_code(500);
            exit('Finalize failed');
        }
    }
} elseif ($type === 'checkout.session.expired' && $orderId > 0) {
    mark_order_expired($pdo, $orderId);
    app_log('high', 'Payment', 'hold released (checkout expired)', ['order_id' => $orderId]);
} elseif ($type === 'checkout.session.async_payment_failed' && $orderId > 0) {
    mark_order_expired($pdo, $orderId);
    app_log('high', 'Payment', 'async payment failed; hold released', ['order_id' => $orderId]);
}

http_response_code(200);
echo 'OK';
