<?php
/**
 * Stripe redirect target. Confirms the payment (idempotent with the webhook)
 * and shows the order summary. Also handles free RSVP confirmations.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/boxes.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/order_summary.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/vendor/autoload.php';

auth_start_session();

$order  = null;
$isRsvp = isset($_GET['rsvp']) && $_GET['rsvp'] === '1';

if ($isRsvp) {
    $orderId = (int) ($_SESSION['rsvp_order_id'] ?? 0);
    unset($_SESSION['rsvp_order_id']);
    if ($orderId > 0) {
        $candidate = order_get_with_items($pdo, $orderId);
        if ($candidate && ($candidate['payment_method'] ?? '') === 'rsvp' && $candidate['status'] === 'paid') {
            $order = $candidate;
        }
    }
    if ($order === null) {
        header('Location: ' . APP_URL . '/order', true, 302);
        exit;
    }
} else {
    $sessionId = $_GET['session_id'] ?? '';
    if ($sessionId === '') {
        header('Location: ' . APP_URL . '/order', true, 302);
        exit;
    }

    try {
        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
        $session = \Stripe\Checkout\Session::retrieve($sessionId);

        if ($session
            && $session->payment_status === 'paid'
            && ($session->metadata->source ?? '') === 'dolos'
            && !empty($session->metadata->order_id)
        ) {
            $orderId = (int) $session->metadata->order_id;
            $amount  = isset($session->amount_total) ? (int) $session->amount_total : null;
            payment_finalize_and_notify($pdo, $orderId, $sessionId, $amount);
            $order = order_get_with_items($pdo, $orderId);
            if (isset($_SESSION['stripe_pay'])) {
                unset($_SESSION['stripe_pay']);
            }
        } else {
            app_log('high', 'Payment', 'success page: not paid / bad metadata', [
                'stripe_session_id' => $sessionId,
                'payment_status'    => $session->payment_status ?? null,
            ]);
        }
    } catch (Throwable $e) {
        app_log('high', 'Payment', 'success page error', ['error' => $e->getMessage()]);
    }
}

layout_head('Thank you');
?>
<?php if ($order && $order['status'] === 'paid'): ?>
  <?php
    $confirmEmail = trim((string) ($order['email'] ?? ''));
    $confirmEmailOk = $confirmEmail !== '' && filter_var($confirmEmail, FILTER_VALIDATE_EMAIL);
    $isRsvpOrder = ($order['payment_method'] ?? '') === 'rsvp';
  ?>
  <div class="card text-center mb-6">
    <h1 class="text-2xl font-bold text-indigo-900"><?= $isRsvpOrder ? 'RSVP confirmed!' : 'Your order is confirmed!' ?></h1>
    <?php if ($confirmEmailOk): ?>
      <p class="text-gray-600 mt-2">A confirmation email is on its way to <strong><?= e($confirmEmail) ?></strong>.</p>
    <?php else: ?>
      <p class="text-gray-600 mt-2">No email was provided — a confirmation was sent to the church office<?= trim((string) ($order['phone'] ?? '')) !== '' ? ' (phone on file: <strong>' . e($order['phone']) . '</strong>)' : '' ?>.</p>
    <?php endif; ?>
  </div>
  <?php render_order_summary($order, [
      'show_order_id' => true,
      'total_label'   => $isRsvpOrder ? 'Total' : 'Total Paid',
  ]); ?>
  <div class="mt-6 text-center space-y-2">
    <p class="text-gray-700">You can now close this page.</p>
    <p><a href="<?= e(APP_URL) ?>/order" class="text-indigo-600 underline hover:text-indigo-800">再訂購午餐</a></p>
  </div>
<?php else: ?>
  <div class="card text-center">
    <h1 class="text-xl font-bold text-gray-800">Payment received</h1>
    <p class="text-gray-600 mt-2">If you just paid, your order is being finalized. You'll get a confirmation email shortly.</p>
    <div class="mt-4 space-y-2">
      <p class="text-gray-700">You can now close this page.</p>
      <p><a href="<?= e(APP_URL) ?>/order" class="text-indigo-600 underline hover:text-indigo-800">再訂購午餐</a></p>
    </div>
  </div>
<?php endif; ?>
<?php layout_footer(); ?>
