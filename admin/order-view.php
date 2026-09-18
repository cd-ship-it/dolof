<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/boxes.php';
require_once dirname(__DIR__) . '/includes/orders.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/order_summary.php';
require_once dirname(__DIR__) . '/includes/layout.php';

require_admin();

$order = order_get_with_items($pdo, (int) ($_GET['id'] ?? 0));

admin_head('Order', 'orders');

if (!$order) {
    echo '<p class="text-gray-600">Order not found. <a class="text-indigo-600 hover:underline" href="' . e(APP_URL) . '/admin/orders">Back to orders</a></p></main></body></html>';
    exit;
}
?>
<a href="<?= e(APP_URL) ?>/admin/orders" class="text-sm text-indigo-600 hover:underline">← All orders</a>
<h1 class="text-2xl font-bold text-gray-900 mt-2 mb-4">Order #<?= (int) $order['id'] ?></h1>

<?php if ((int) $order['capacity_flag'] === 1): ?>
  <div class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-2 text-sm text-red-800">
    ⚑ Flagged: paid over capacity — <?= e($order['flag_reason']) ?>
  </div>
<?php endif; ?>

<?php
render_order_summary($order, [
    'heading'       => 'Order',
    'show_order_id' => true,
    'total_label'   => (($order['payment_method'] ?? '') === 'rsvp') ? 'Total' : 'Total paid',
    'card_class'    => 'bg-white rounded-xl border !shadow-none',
]);
?>

<div class="bg-white rounded-xl border p-4 text-sm space-y-1 mt-4">
  <div><span class="text-gray-500">Registrant:</span> <?= e(trim($order['first_name'] . ' ' . $order['last_name'])) ?></div>
  <div><span class="text-gray-500">Status:</span> <?= e($order['status']) ?></div>
  <div><span class="text-gray-500">Payment:</span> <?= e($order['payment_method'] ?? 'stripe') ?></div>
  <div><span class="text-gray-500">Placed:</span> <?= e($order['created_at']) ?></div>
  <div><span class="text-gray-500">Stripe session:</span> <span class="font-mono text-xs"><?= e($order['stripe_session_id']) ?></span></div>
  <div><span class="text-gray-500">Confirmation email:</span> <?= ((int) $order['confirmation_email_sent'] === 1) ? 'sent' : 'not sent' ?></div>
</div>
</main></body></html>
