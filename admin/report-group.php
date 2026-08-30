<?php
/**
 * Drill-down page for the Campus → Life Group report: the individual orders
 * for one campus + life group. Linked from admin/report.
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/boxes.php';
require_once dirname(__DIR__) . '/includes/layout.php';

require_admin();

$status = $_GET['status'] ?? 'paid';
if (!in_array($status, ['paid', 'all'], true)) {
    $status = 'paid';
}
$statusSql = $status === 'all'
    ? "(o.status = 'paid' OR (o.status = 'pending' AND o.hold_expires_at IS NOT NULL AND o.hold_expires_at > NOW()))"
    : "o.status = 'paid'";

const NO_CAMPUS = '(no campus)';
const NO_GROUP  = '(no life group)';

$campusLabel = (string) ($_GET['campus'] ?? '');
$groupLabel  = (string) ($_GET['lg'] ?? '');
$campusRaw   = $campusLabel === NO_CAMPUS ? '' : $campusLabel;
$groupRaw    = $groupLabel === NO_GROUP ? '' : $groupLabel;

$stmt = $pdo->prepare(
    "SELECT o.*,
            GROUP_CONCAT(CONCAT(oi.box_code, ' ×', oi.quantity) ORDER BY oi.box_code SEPARATOR ', ') AS items_summary
       FROM " . DOLOS_TBL_ORDERS . " o
       LEFT JOIN " . DOLOS_TBL_ITEMS . " oi ON oi.order_id = o.id
      WHERE {$statusSql} AND o.campus = ? AND o.lift_group = ?
      GROUP BY o.id
      ORDER BY o.last_name, o.first_name, o.created_at"
);
$stmt->execute([$campusRaw, $groupRaw]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grand = 0;
foreach ($orders as $o) {
    $grand += (int) $o['total_amount_cents'];
}

$btStmt = $pdo->prepare(
    "SELECT oi.box_code, SUM(oi.quantity) AS qty
       FROM " . DOLOS_TBL_ORDERS . " o
       JOIN " . DOLOS_TBL_ITEMS . " oi ON oi.order_id = o.id
      WHERE {$statusSql} AND o.campus = ? AND o.lift_group = ?
      GROUP BY oi.box_code
      ORDER BY oi.box_code"
);
$btStmt->execute([$campusRaw, $groupRaw]);
$boxTotals = [];
foreach ($btStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $boxTotals[$r['box_code']] = (int) $r['qty'];
}

$backUrl = APP_URL . '/admin/report?' . http_build_query(['status' => $status]);

admin_head('Report — ' . ($campusLabel !== '' ? $campusLabel : NO_CAMPUS), 'report');
?>
<div class="flex items-center justify-between gap-3 mb-2">
  <a href="<?= e($backUrl) ?>" class="text-sm text-indigo-600 hover:underline">← Back to report</a>
</div>

<h1 class="text-2xl font-bold text-gray-900">
  <?= e($campusLabel !== '' ? $campusLabel : NO_CAMPUS) ?>
  <span class="text-gray-400 font-normal">·</span>
  <?= e($groupLabel !== '' ? $groupLabel : NO_GROUP) ?>
</h1>
<p class="text-sm text-gray-500 mb-4">
  <?= count($orders) ?> order<?= count($orders) === 1 ? '' : 's' ?>
  <?php if ($boxTotals): ?>
    &middot; <?= e(implode(', ', array_map(fn($c, $n) => $c . ' ×' . $n, array_keys($boxTotals), $boxTotals))) ?>
  <?php endif; ?>
  &middot; <?= e(money($grand)) ?> total
  &middot; <?= $status === 'paid' ? 'paid orders only' : 'paid + held' ?>
</p>

<div class="bg-white rounded-xl border overflow-x-auto">
  <table class="min-w-full text-sm">
    <thead class="bg-gray-50 text-left text-gray-600 border-b">
      <tr>
        <th class="px-3 py-2">#</th>
        <th class="px-3 py-2">Name</th>
        <th class="px-3 py-2">Email</th>
        <th class="px-3 py-2">Phone</th>
        <th class="px-3 py-2">Boxes</th>
        <th class="px-3 py-2 text-right">Total</th>
        <th class="px-3 py-2">Status</th>
        <th class="px-3 py-2">Placed</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
      <tr class="border-b border-gray-100 hover:bg-gray-50">
        <td class="px-3 py-2">
          <a href="<?= e(APP_URL) ?>/admin/order-view?id=<?= (int) $o['id'] ?>" class="text-indigo-700 hover:underline"><?= (int) $o['id'] ?></a>
          <?php if ((int) $o['capacity_flag'] === 1): ?><span title="<?= e($o['flag_reason']) ?>" class="ml-1 text-red-600">⚑</span><?php endif; ?>
        </td>
        <td class="px-3 py-2"><?= e(trim($o['first_name'] . ' ' . $o['last_name'])) ?></td>
        <td class="px-3 py-2"><?= e($o['email']) ?></td>
        <td class="px-3 py-2"><?= e($o['phone']) ?></td>
        <td class="px-3 py-2 font-mono text-xs"><?= e($o['items_summary'] ?? '') ?></td>
        <td class="px-3 py-2 text-right"><?= e(money((int) $o['total_amount_cents'])) ?></td>
        <td class="px-3 py-2"><?= e($o['status']) ?></td>
        <td class="px-3 py-2 whitespace-nowrap text-gray-500"><?= e(date('M j, g:ia', strtotime($o['created_at']))) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$orders): ?>
      <tr><td colspan="8" class="px-3 py-6 text-center text-gray-500">No orders in this group.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
</main></body></html>
