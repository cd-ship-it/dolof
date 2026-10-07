<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/boxes.php';
require_once dirname(__DIR__) . '/includes/orders.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/order_summary.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

require_admin();

$orderId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$notice  = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'refund' && $orderId > 0) {
        $result = order_refund($pdo, $orderId);
        if ($result['ok']) {
            $notice = 'Order refunded. Lunch box seats have been released.';
        } else {
            $error = $result['error'] ?? 'Refund failed.';
        }
    }
}

$order = order_get_with_items($pdo, $orderId);

admin_head('Order', 'orders');

if (!$order) {
    echo '<p class="text-gray-600">Order not found. <a class="text-indigo-600 hover:underline" href="' . e(APP_URL) . '/admin/orders">Back to orders</a></p></main></body></html>';
    exit;
}

$canRefund = ($order['status'] ?? '') === 'paid';
$totalLabel = (($order['payment_method'] ?? '') === 'rsvp')
    ? 'Total'
    : (($order['status'] ?? '') === 'refunded' ? 'Total (refunded)' : 'Total paid');
?>
<a href="<?= e(APP_URL) ?>/admin/orders" class="text-sm text-indigo-600 hover:underline">← All orders</a>
<h1 class="text-2xl font-bold text-gray-900 mt-2 mb-4">Order #<?= (int) $order['id'] ?></h1>

<?php if ($notice): ?>
  <div class="mb-4 rounded-md bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800"><?= e($notice) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-2 text-sm text-red-800"><?= e($error) ?></div>
<?php endif; ?>

<?php if ((int) $order['capacity_flag'] === 1): ?>
  <div class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-2 text-sm text-red-800">
    ⚑ Flagged: paid over capacity — <?= e($order['flag_reason']) ?>
  </div>
<?php endif; ?>

<?php
render_order_summary($order, [
    'heading'       => 'Order',
    'show_order_id' => true,
    'total_label'   => $totalLabel,
    'card_class'    => 'bg-white rounded-xl border !shadow-none',
]);
?>

<div class="bg-white rounded-xl border p-4 text-sm space-y-1 mt-4">
  <div><span class="text-gray-500">Registrant:</span> <?= e(trim($order['first_name'] . ' ' . $order['last_name'])) ?></div>
  <div>
    <span class="text-gray-500">Status:</span>
    <?php if (($order['status'] ?? '') === 'refunded'): ?>
      <span class="inline-flex items-center rounded-md bg-amber-50 border border-amber-200 px-2 py-0.5 text-amber-800 font-medium">refunded</span>
    <?php else: ?>
      <?= e($order['status']) ?>
    <?php endif; ?>
  </div>
  <div><span class="text-gray-500">Payment:</span> <?= e($order['payment_method'] ?? 'stripe') ?></div>
  <div><span class="text-gray-500">Placed:</span> <?= e($order['created_at']) ?></div>
  <div><span class="text-gray-500">Stripe session:</span> <span class="font-mono text-xs"><?= e($order['stripe_session_id']) ?></span></div>
  <div><span class="text-gray-500">Confirmation email:</span> <?= ((int) $order['confirmation_email_sent'] === 1) ? 'sent' : 'not sent' ?></div>
</div>

<?php if ($canRefund): ?>
  <div class="mt-4">
    <button type="button" id="refund-open"
            class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
      Refund
    </button>
  </div>

  <div id="refund-modal" class="hidden fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-3">
    <div class="my-8 w-full max-w-md rounded-xl bg-white p-5 shadow-xl space-y-4 text-sm">
      <h2 class="text-base font-bold text-gray-900">Refund confirm?</h2>
      <p class="text-gray-600">
        Refund order #<?= (int) $order['id'] ?>
        (<?= e(money((int) $order['total_amount_cents'])) ?>)
        for <?= e(trim($order['first_name'] . ' ' . $order['last_name'])) ?>?
        This releases the lunch box seats and cannot be undone from here.
      </p>
      <form method="post" class="flex flex-col gap-2">
        <?= csrf_input() ?>
        <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
        <input type="hidden" name="action" value="refund">
        <button type="submit"
                class="w-full rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
          Confirm refund
        </button>
        <button type="button" id="refund-back"
                class="w-full rounded-md border-2 border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
          Back
        </button>
      </form>
    </div>
  </div>

  <script>
  (function () {
    var modal = document.getElementById('refund-modal');
    var openBtn = document.getElementById('refund-open');
    var backBtn = document.getElementById('refund-back');
    if (!modal || !openBtn) return;

    function open() { modal.classList.remove('hidden'); }
    function close() { modal.classList.add('hidden'); }

    openBtn.addEventListener('click', open);
    if (backBtn) backBtn.addEventListener('click', close);
    modal.addEventListener('click', function (e) {
      if (e.target === modal) close();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !modal.classList.contains('hidden')) close();
    });
  })();
  </script>
<?php endif; ?>
</main></body></html>
