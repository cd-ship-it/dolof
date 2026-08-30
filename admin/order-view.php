<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/boxes.php';
require_once dirname(__DIR__) . '/includes/orders.php';
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

<div class="grid sm:grid-cols-2 gap-4">
  <div class="bg-white rounded-xl border p-4 text-sm space-y-1">
    <div><span class="text-gray-500">Name:</span> <?= e(trim($order['first_name'] . ' ' . $order['last_name'])) ?></div>
    <div><span class="text-gray-500">Email:</span> <?= e($order['email']) ?></div>
    <div><span class="text-gray-500">Phone:</span> <?= e($order['phone']) ?></div>
    <div><span class="text-gray-500">Campus:</span> <?= e($order['campus']) ?></div>
    <div><span class="text-gray-500">Lift Group:</span> <?= e($order['lift_group']) ?></div>
    <div><span class="text-gray-500">Status:</span> <?= e($order['status']) ?></div>
    <div><span class="text-gray-500">Placed:</span> <?= e($order['created_at']) ?></div>
    <div><span class="text-gray-500">Stripe session:</span> <span class="font-mono text-xs"><?= e($order['stripe_session_id']) ?></span></div>
    <div><span class="text-gray-500">Confirmation email:</span> <?= ((int) $order['confirmation_email_sent'] === 1) ? 'sent' : 'not sent' ?></div>
  </div>

  <div class="bg-white rounded-xl border p-4">
    <table class="w-full text-sm">
      <thead><tr class="text-left text-gray-500 border-b"><th class="py-1">Box</th><th class="py-1 text-center">Qty</th><th class="py-1 text-right">Subtotal</th></tr></thead>
      <tbody>
      <?php foreach ($order['items'] as $it): ?>
        <tr class="border-b border-gray-100">
          <td class="py-2"><?= e($it['box_code'] . ' — ' . $it['box_name']) ?></td>
          <td class="py-2 text-center"><?= (int) $it['quantity'] ?></td>
          <td class="py-2 text-right"><?= e(money((int) $it['unit_price_cents'] * (int) $it['quantity'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td class="pt-3 font-semibold" colspan="2">Total</td><td class="pt-3 font-semibold text-right"><?= e(money((int) $order['total_amount_cents'])) ?></td></tr></tfoot>
    </table>
  </div>
</div>
</main></body></html>
