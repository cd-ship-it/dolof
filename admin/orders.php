<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/boxes.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/order_summary.php';
require_once dirname(__DIR__) . '/includes/layout.php';

require_admin();

$statusFilter = $_GET['status'] ?? 'paid';
$allowed      = ['paid', 'pending', 'expired', 'cancelled', 'all'];
if (!in_array($statusFilter, $allowed, true)) {
    $statusFilter = 'paid';
}
$boxFilter = trim($_GET['box'] ?? '');

$sql = 'SELECT o.* FROM ' . DOLOS_TBL_ORDERS . ' o';
$where  = [];
$params = [];
if ($statusFilter !== 'all') {
    $where[] = 'o.status = ?';
    $params[] = $statusFilter;
}
if ($boxFilter !== '') {
    // Match attendees who chose this box (order_items is the rolled-up mirror).
    $where[] = 'o.id IN (SELECT order_id FROM ' . DOLOS_TBL_ITEMS . ' WHERE box_code = ?)';
    $params[] = $boxFilter;
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY o.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$boxes = get_all_boxes($pdo);

admin_head('Orders', 'orders');
?>
<div class="flex items-center justify-between mb-4">
  <h1 class="text-2xl font-bold text-gray-900">Orders</h1>
  <a href="<?= e(APP_URL) ?>/admin/export" class="rounded-md bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-700">Export paid (CSV)</a>
</div>

<form method="get" class="mb-4 flex flex-wrap gap-3 text-sm">
  <select name="status" class="rounded border-gray-300">
    <?php foreach (['paid', 'pending', 'expired', 'cancelled', 'all'] as $s): ?>
      <option value="<?= $s ?>" <?= $s === $statusFilter ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="box" class="rounded border-gray-300">
    <option value="">All lunch boxes</option>
    <?php foreach ($boxes as $b): ?>
      <option value="<?= e($b['code']) ?>" <?= $b['code'] === $boxFilter ? 'selected' : '' ?>><?= e($b['code'] . ' — ' . $b['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="rounded-md border px-3 py-1.5 hover:bg-gray-50">Filter</button>
</form>

<div class="bg-white rounded-xl border overflow-x-auto">
  <table class="min-w-full text-sm">
    <thead class="text-left text-gray-500 border-b bg-gray-50">
      <tr>
        <th class="px-3 py-2">#</th>
        <th class="px-3 py-2">Registrant</th>
        <th class="px-3 py-2">Contact</th>
        <th class="px-3 py-2">Campus</th>
        <th class="px-3 py-2">Lift Group</th>
        <th class="px-3 py-2">Attendance (name · lunch)</th>
        <th class="px-3 py-2 text-right">Total</th>
        <th class="px-3 py-2">Status</th>
        <th class="px-3 py-2">Placed</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
      <?php
        $attLines = order_attendance_lines($o);
        $contact = trim((string) ($o['email'] ?? ''));
        if ($contact === '') {
            $contact = trim((string) ($o['phone'] ?? ''));
        } elseif (trim((string) ($o['phone'] ?? '')) !== '') {
            $contact .= ' · ' . trim((string) $o['phone']);
        }
      ?>
      <tr class="border-b border-gray-100 hover:bg-gray-50 align-top">
        <td class="px-3 py-2">
          <a href="<?= e(APP_URL) ?>/admin/order-view?id=<?= (int) $o['id'] ?>" class="text-indigo-700 hover:underline"><?= (int) $o['id'] ?></a>
          <?php if ((int) $o['capacity_flag'] === 1): ?><span title="<?= e($o['flag_reason']) ?>" class="ml-1 text-red-600">⚑</span><?php endif; ?>
        </td>
        <td class="px-3 py-2 font-medium"><?= e(trim($o['first_name'] . ' ' . $o['last_name'])) ?></td>
        <td class="px-3 py-2 text-gray-600"><?= e($contact) ?></td>
        <td class="px-3 py-2"><?= e($o['campus']) ?></td>
        <td class="px-3 py-2"><?= e($o['lift_group']) ?></td>
        <td class="px-3 py-2">
          <?php if ($attLines): ?>
            <ul class="space-y-0.5 text-xs text-gray-800">
              <?php foreach ($attLines as $line): ?>
                <li><?= e($line) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <span class="text-gray-400">—</span>
          <?php endif; ?>
        </td>
        <td class="px-3 py-2 text-right whitespace-nowrap"><?= e(money((int) $o['total_amount_cents'])) ?></td>
        <td class="px-3 py-2"><?= e($o['status']) ?></td>
        <td class="px-3 py-2 whitespace-nowrap text-gray-500"><?= e(date('M j, g:ia', strtotime($o['created_at']))) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$orders): ?>
      <tr><td colspan="9" class="px-3 py-6 text-center text-gray-500">No orders match.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<p class="text-xs text-gray-500 mt-2">Attendance lists each person with their lunch choice (or 留位但不點餐).</p>
</main></body></html>
