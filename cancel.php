<?php
/**
 * Stripe "cancel" target. Releases the hold right away and sends the customer
 * back to the form.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/orders.php';

auth_start_session();

$orderId = (int) ($_GET['order'] ?? 0);
if ($orderId > 0) {
    if (mark_order_cancelled($pdo, $orderId)) {
        app_log('high', 'Payment', 'checkout cancelled by user', ['order_id' => $orderId]);
    }
    $pay = $_SESSION['stripe_pay'] ?? null;
    if (is_array($pay) && (int) ($pay['order_id'] ?? 0) === $orderId) {
        unset($_SESSION['stripe_pay']);
    }
}

header('Location: ' . APP_URL . '/order?cancelled=1', true, 302);
exit;
