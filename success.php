<?php
/**
 * Stripe redirect target. Confirms the payment (idempotent with the webhook)
 * and shows the order summary.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/boxes.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/vendor/autoload.php';

$sessionId = $_GET['session_id'] ?? '';
if ($sessionId === '') {
    header('Location: ' . APP_URL . '/order', true, 302);
    exit;
}

$order = null;

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
    } else {
        app_log('high', 'Payment', 'success page: not paid / bad metadata', [
            'stripe_session_id' => $sessionId,
            'payment_status'    => $session->payment_status ?? null,
        ]);
    }
} catch (Throwable $e) {
    app_log('high', 'Payment', 'success page error', ['error' => $e->getMessage()]);
}

layout_head('Thank you — Deacons Ordination Lunch Ordering Form');
?>
<?php if ($order && $order['status'] === 'paid'): ?>
  <div class="card text-center mb-6">
    <div class="text-4xl mb-2">🎉</div>
    <h1 class="text-2xl font-bold text-indigo-900">Order confirmed!</h1>
    <p class="text-gray-600 mt-2">A confirmation email is on its way to <strong><?= e($order['email']) ?></strong>.</p>
  </div>
  <div class="card">
    <h2 class="font-semibold text-gray-900 mb-3">Order #<?= (int) $order['id'] ?></h2>
    <p class="text-sm text-gray-600 mb-4">
      <?= e(trim($order['first_name'] . ' ' . $order['last_name'])) ?> &middot; <?= e($order['phone']) ?><br>
      <span class="text-gray-500">Campus:</span> <?= e($order['campus']) ?>
      &middot; <span class="text-gray-500">Lift Group:</span> <?= e($order['lift_group']) ?>
    </p>
    <table class="w-full text-sm">
      <thead><tr class="text-left text-gray-500 border-b">
        <th class="py-1">Box</th><th class="py-1 text-center">Qty</th><th class="py-1 text-right">Subtotal</th>
      </tr></thead>
      <tbody>
      <?php foreach ($order['items'] as $it): ?>
        <tr class="border-b border-gray-100">
          <td class="py-2"><?= e($it['box_name']) ?></td>
          <td class="py-2 text-center"><?= (int) $it['quantity'] ?></td>
          <td class="py-2 text-right"><?= e(money((int) $it['unit_price_cents'] * (int) $it['quantity'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr>
        <td class="pt-3 font-semibold" colspan="2">Total paid</td>
        <td class="pt-3 font-semibold text-right"><?= e(money((int) $order['total_amount_cents'])) ?></td>
      </tr></tfoot>
    </table>
  </div>
  <div class="text-center mt-6">
    <a href="<?= e(APP_URL) ?>/order" target="_blank" rel="noopener" class="btn-primary inline-block">Order more</a>
    <p class="text-xs text-gray-400 mt-2">Opens a fresh order form in a new tab.</p>
  </div>
<?php else: ?>
  <div class="card text-center">
    <h1 class="text-xl font-bold text-gray-800">Payment received</h1>
    <p class="text-gray-600 mt-2">If you just paid, your order is being finalized. You'll get a confirmation email shortly — you may close this page.</p>
    <p class="mt-4"><a href="<?= e(APP_URL) ?>/order" class="btn-primary inline-block">Back to ordering</a></p>
  </div>
<?php endif; ?>
<?php layout_footer(); ?>
