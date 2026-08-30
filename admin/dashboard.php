<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/boxes.php';
require_once dirname(__DIR__) . '/includes/layout.php';

require_admin();

$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_ordering') {
        $open = ordering_is_open($pdo) ? '0' : '1';
        dolos_setting_set($pdo, 'ordering_open', $open);
        $notice = 'Ordering is now ' . ($open === '1' ? 'OPEN' : 'CLOSED') . '.';
    }

    if ($action === 'save_event') {
        dolos_setting_set($pdo, 'event_title', trim($_POST['event_title'] ?? ''));
        dolos_setting_set($pdo, 'event_date', trim($_POST['event_date'] ?? ''));
        dolos_setting_set($pdo, 'event_location', trim($_POST['event_location'] ?? ''));
        $notice = 'Event details saved.';
    }

    if ($action === 'save_boxes') {
        $stmt = $pdo->prepare(
            'UPDATE ' . DOLOS_TBL_BOXES . ' SET name = ?, price_cents = ?, cap = ?, active = ? WHERE id = ?'
        );
        foreach (($_POST['box'] ?? []) as $id => $row) {
            $id    = (int) $id;
            $name  = trim($row['name'] ?? '');
            $price = (int) round((float) ($row['price'] ?? 0) * 100);
            $cap   = max(0, (int) ($row['cap'] ?? 0));
            $active = isset($row['active']) ? 1 : 0;
            if ($id > 0 && $name !== '') {
                $stmt->execute([$name, max(0, $price), $cap, $active, $id]);
            }
        }
        $notice = 'Lunch boxes updated.';
    }
}

$boxes  = get_all_boxes($pdo);
$paid   = box_paid_counts($pdo);
$held   = box_held_counts($pdo);
$open   = ordering_is_open($pdo);

$totals = $pdo->query(
    "SELECT COUNT(*) AS orders, COALESCE(SUM(total_amount_cents),0) AS revenue
       FROM " . DOLOS_TBL_ORDERS . " WHERE status = 'paid'"
)->fetch(PDO::FETCH_ASSOC);
$heldOrders = (int) $pdo->query(
    "SELECT COUNT(*) FROM " . DOLOS_TBL_ORDERS . "
      WHERE status = 'pending' AND hold_expires_at IS NOT NULL AND hold_expires_at > NOW()"
)->fetchColumn();

$eventTitle    = dolos_setting($pdo, 'event_title', '');
$eventDate     = dolos_setting($pdo, 'event_date', '');
$eventLocation = dolos_setting($pdo, 'event_location', '');
$csrf = csrf_input();

admin_head('Dashboard', 'dashboard');
?>
<h1 class="text-2xl font-bold text-gray-900 mb-4">Dashboard</h1>

<?php if ($notice): ?>
  <div class="mb-4 rounded-md bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800"><?= e($notice) ?></div>
<?php endif; ?>

<div class="grid sm:grid-cols-3 gap-4 mb-6">
  <div class="bg-white rounded-xl border p-4">
    <div class="text-xs uppercase text-gray-500">Paid orders</div>
    <div class="text-2xl font-bold"><?= (int) $totals['orders'] ?></div>
  </div>
  <div class="bg-white rounded-xl border p-4">
    <div class="text-xs uppercase text-gray-500">Revenue</div>
    <div class="text-2xl font-bold"><?= e(money((int) $totals['revenue'])) ?></div>
  </div>
  <div class="bg-white rounded-xl border p-4">
    <div class="text-xs uppercase text-gray-500">Active holds</div>
    <div class="text-2xl font-bold"><?= $heldOrders ?></div>
  </div>
</div>

<div class="bg-white rounded-xl border p-4 mb-6 flex items-center justify-between">
  <div>
    <div class="font-semibold text-gray-900">Online ordering</div>
    <div class="text-sm <?= $open ? 'text-emerald-700' : 'text-red-600' ?>"><?= $open ? 'OPEN — accepting orders' : 'CLOSED' ?></div>
  </div>
  <form method="post">
    <?= $csrf ?>
    <input type="hidden" name="action" value="toggle_ordering">
    <button class="rounded-md border px-4 py-2 text-sm font-medium hover:bg-gray-50"><?= $open ? 'Close ordering' : 'Open ordering' ?></button>
  </form>
</div>

<form method="post" class="bg-white rounded-xl border p-4 mb-6">
  <?= $csrf ?>
  <input type="hidden" name="action" value="save_boxes">
  <h2 class="font-semibold text-gray-900 mb-3">Lunch boxes</h2>
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="text-left text-gray-500 border-b">
        <tr>
          <th class="py-2 pr-3">Code</th>
          <th class="py-2 pr-3">Name</th>
          <th class="py-2 pr-3">Price ($)</th>
          <th class="py-2 pr-3">Cap</th>
          <th class="py-2 pr-3 text-center">Paid</th>
          <th class="py-2 pr-3 text-center">Held</th>
          <th class="py-2 pr-3 text-center">Remaining</th>
          <th class="py-2 pr-3 text-center">Active</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($boxes as $b):
        $bid = (int) $b['id'];
        $p = $paid[$bid] ?? 0;
        $h = $held[$bid] ?? 0;
        $remaining = max(0, (int) $b['cap'] - $p - $h);
      ?>
        <tr class="border-b border-gray-100">
          <td class="py-2 pr-3 font-mono"><?= e($b['code']) ?></td>
          <td class="py-2 pr-3"><input type="text" name="box[<?= $bid ?>][name]" value="<?= e($b['name']) ?>" class="w-48 rounded border-gray-300 text-sm"></td>
          <td class="py-2 pr-3"><input type="number" step="0.01" min="0" name="box[<?= $bid ?>][price]" value="<?= number_format($b['price_cents'] / 100, 2, '.', '') ?>" class="w-24 rounded border-gray-300 text-sm"></td>
          <td class="py-2 pr-3"><input type="number" min="0" name="box[<?= $bid ?>][cap]" value="<?= (int) $b['cap'] ?>" class="w-20 rounded border-gray-300 text-sm"></td>
          <td class="py-2 pr-3 text-center"><?= $p ?></td>
          <td class="py-2 pr-3 text-center text-amber-600"><?= $h ?></td>
          <td class="py-2 pr-3 text-center font-semibold <?= $remaining === 0 ? 'text-red-600' : 'text-emerald-700' ?>"><?= $remaining ?></td>
          <td class="py-2 pr-3 text-center"><input type="checkbox" name="box[<?= $bid ?>][active]" <?= (int) $b['active'] === 1 ? 'checked' : '' ?>></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <button class="mt-3 btn-primary rounded-md bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-700">Save boxes</button>
  <a href="<?= e(APP_URL) ?>/admin/export" class="ml-3 text-sm text-indigo-600 hover:underline">Export paid orders (CSV)</a>
</form>

<form method="post" class="bg-white rounded-xl border p-4">
  <?= $csrf ?>
  <input type="hidden" name="action" value="save_event">
  <h2 class="font-semibold text-gray-900 mb-3">Event details (shown on confirmation email)</h2>
  <div class="grid sm:grid-cols-3 gap-3">
    <label class="block text-sm">Title
      <input type="text" name="event_title" value="<?= e($eventTitle) ?>" class="mt-1 w-full rounded border-gray-300 text-sm">
    </label>
    <label class="block text-sm">Date
      <input type="text" name="event_date" value="<?= e($eventDate) ?>" placeholder="Sunday, Oct 12, 2026" class="mt-1 w-full rounded border-gray-300 text-sm">
    </label>
    <label class="block text-sm">Location
      <input type="text" name="event_location" value="<?= e($eventLocation) ?>" class="mt-1 w-full rounded border-gray-300 text-sm">
    </label>
  </div>
  <button class="mt-3 rounded-md bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-700">Save event details</button>
</form>
</main></body></html>
