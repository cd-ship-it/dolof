<?php
/**
 * Campus → Life Group order report.
 *
 * Per Campus/Life Group: how many of each lunch box were ordered (with full
 * dish names), ordered by Campus then Life Group, with campus subtotals and a
 * grand total. CSV export via ?export=csv. Click a Life Group to open its
 * individual orders on admin/report-group.
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/boxes.php';
require_once dirname(__DIR__) . '/includes/layout.php';

require_admin();

// ── Filters ─────────────────────────────────────────────────────────────────
$status = $_GET['status'] ?? 'paid';
if (!in_array($status, ['paid', 'all'], true)) {
    $status = 'paid';
}
// "all" = paid + still-held pending. "paid" = confirmed only.
$statusSql = $status === 'all'
    ? "(o.status = 'paid' OR (o.status = 'pending' AND o.hold_expires_at IS NOT NULL AND o.hold_expires_at > NOW()))"
    : "o.status = 'paid'";

$boxes = get_all_boxes($pdo);                        // column order + full names
$codes = array_column($boxes, 'code');
$names = array_column($boxes, 'name', 'code');

const NO_CAMPUS = '(no campus)';
const NO_GROUP  = '(no life group)';

// ── Aggregate: revenue per Campus/Life Group ────────────────────────────────
$rows = $pdo->query(
    "SELECT o.campus, o.lift_group,
            COALESCE(SUM(o.total_amount_cents), 0) AS revenue_cents
       FROM " . DOLOS_TBL_ORDERS . " o
      WHERE {$statusSql}
      GROUP BY o.campus, o.lift_group
      ORDER BY o.campus, o.lift_group"
)->fetchAll(PDO::FETCH_ASSOC);

// ── Aggregate: box quantities per Campus/Life Group ─────────────────────────
$qtyRows = $pdo->query(
    "SELECT o.campus, o.lift_group, oi.box_code, SUM(oi.quantity) AS qty
       FROM " . DOLOS_TBL_ORDERS . " o
       JOIN " . DOLOS_TBL_ITEMS . " oi ON oi.order_id = o.id
      WHERE {$statusSql}
      GROUP BY o.campus, o.lift_group, oi.box_code"
)->fetchAll(PDO::FETCH_ASSOC);

$key = fn($c, $g) => ($c === '' ? NO_CAMPUS : $c) . "\x1f" . ($g === '' ? NO_GROUP : $g);

$groups = [];
foreach ($rows as $r) {
    $groups[$key($r['campus'], $r['lift_group'])] = [
        'campus'     => $r['campus'] === '' ? NO_CAMPUS : $r['campus'],
        'lift_group' => $r['lift_group'] === '' ? NO_GROUP : $r['lift_group'],
        'campus_raw' => $r['campus'],
        'group_raw'  => $r['lift_group'],
        'revenue'    => (int) $r['revenue_cents'],
        'qty'        => array_fill_keys($codes, 0),
    ];
}
foreach ($qtyRows as $r) {
    $k = $key($r['campus'], $r['lift_group']);
    if (isset($groups[$k]) && array_key_exists($r['box_code'], $groups[$k]['qty'])) {
        $groups[$k]['qty'][$r['box_code']] = (int) $r['qty'];
    }
}
ksort($groups);

$grand = ['revenue' => 0, 'qty' => array_fill_keys($codes, 0)];
foreach ($groups as $g) {
    $grand['revenue'] += $g['revenue'];
    foreach ($codes as $c) {
        $grand['qty'][$c] += $g['qty'][$c];
    }
}

// ── CSV export ─────────────────────────────────────────────────────────────
if (($_GET['export'] ?? '') === 'csv') {
    $filename = 'dolos-report-' . $status . '-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");

    $header = ['Campus', 'Life Group'];
    foreach ($codes as $c) {
        $header[] = $c . ' — ' . ($names[$c] ?? '');
    }
    $header[] = 'Total Boxes';
    $header[] = 'Revenue';
    fputcsv($out, $header);

    foreach ($groups as $g) {
        $line = [$g['campus'], $g['lift_group']];
        $tot = 0;
        foreach ($codes as $c) { $line[] = $g['qty'][$c]; $tot += $g['qty'][$c]; }
        $line[] = $tot;
        $line[] = number_format($g['revenue'] / 100, 2, '.', '');
        fputcsv($out, $line);
    }

    $line = ['TOTAL', ''];
    $tot = 0;
    foreach ($codes as $c) { $line[] = $grand['qty'][$c]; $tot += $grand['qty'][$c]; }
    $line[] = $tot;
    $line[] = number_format($grand['revenue'] / 100, 2, '.', '');
    fputcsv($out, $line);

    fclose($out);
    exit;
}

admin_head('Report', 'report');
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
  <h1 class="text-2xl font-bold text-gray-900">Campus → Life Group report</h1>
  <div class="flex items-center gap-2">
    <form method="get" class="flex items-center gap-2 text-sm">
      <select name="status" class="rounded border-gray-300" onchange="this.form.submit()">
        <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid only</option>
        <option value="all"  <?= $status === 'all'  ? 'selected' : '' ?>>Paid + held</option>
      </select>
      <noscript><button class="rounded border px-2 py-1">Apply</button></noscript>
    </form>
    <a href="<?= e(APP_URL) ?>/admin/report?<?= e(http_build_query(['status' => $status, 'export' => 'csv'])) ?>"
       class="rounded-md bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-700">Export CSV</a>
  </div>
</div>

<div class="bg-white rounded-xl border overflow-x-auto">
  <table class="min-w-full text-sm">
    <thead class="bg-gray-50 text-left text-gray-600 border-b">
      <tr>
        <th class="px-3 py-2">Campus</th>
        <th class="px-3 py-2">Life Group</th>
        <?php foreach ($codes as $c): ?>
          <th class="px-3 py-2 text-center align-bottom">
            <span class="inline-flex items-center justify-center h-5 w-5 rounded bg-indigo-100 text-indigo-800 font-bold text-xs"><?= e($c) ?></span>
            <span class="block mt-1 text-xs font-normal text-gray-500 leading-tight w-24 mx-auto"><?= e($names[$c] ?? '') ?></span>
          </th>
        <?php endforeach; ?>
        <th class="px-3 py-2 text-center">Total boxes</th>
        <th class="px-3 py-2 text-right">Revenue</th>
      </tr>
    </thead>
    <tbody>
    <?php
    if (!$groups) {
        echo '<tr><td colspan="' . (4 + count($codes)) . '" class="px-3 py-6 text-center text-gray-500">No orders yet.</td></tr>';
    }
    $prevCampus = null;
    $campusSub  = null;
    $flushCampusSub = function () use (&$campusSub, &$prevCampus, $codes) {
        if ($campusSub === null) return;
        echo '<tr class="bg-gray-50 font-semibold border-b">';
        echo '<td class="px-3 py-2" colspan="2">' . e($prevCampus) . ' subtotal</td>';
        $t = 0;
        foreach ($codes as $c) { echo '<td class="px-3 py-2 text-center">' . $campusSub['qty'][$c] . '</td>'; $t += $campusSub['qty'][$c]; }
        echo '<td class="px-3 py-2 text-center">' . $t . '</td>';
        echo '<td class="px-3 py-2 text-right">' . e(money($campusSub['revenue'])) . '</td>';
        echo '</tr>';
    };

    foreach ($groups as $g):
        if ($prevCampus !== null && $g['campus'] !== $prevCampus) {
            $flushCampusSub();
            $campusSub = null;
        }
        if ($campusSub === null) {
            $campusSub = ['revenue' => 0, 'qty' => array_fill_keys($codes, 0)];
        }
        $prevCampus = $g['campus'];
        $campusSub['revenue'] += $g['revenue'];
        $rowTotal = 0;
        foreach ($codes as $c) { $campusSub['qty'][$c] += $g['qty'][$c]; $rowTotal += $g['qty'][$c]; }

        $groupUrl = APP_URL . '/admin/report-group?' . http_build_query([
            'status' => $status, 'campus' => $g['campus'], 'lg' => $g['lift_group'],
        ]);
    ?>
      <tr class="border-b border-gray-100 hover:bg-indigo-50/40">
        <td class="px-3 py-2 text-gray-500"><?= e($g['campus']) ?></td>
        <td class="px-3 py-2">
          <a href="<?= e($groupUrl) ?>" class="font-medium text-indigo-700 hover:underline"><?= e($g['lift_group']) ?></a>
        </td>
        <?php foreach ($codes as $c): ?>
          <td class="px-3 py-2 text-center <?= $g['qty'][$c] ? 'font-medium' : 'text-gray-300' ?>"><?= $g['qty'][$c] ?: 0 ?></td>
        <?php endforeach; ?>
        <td class="px-3 py-2 text-center font-medium"><?= $rowTotal ?></td>
        <td class="px-3 py-2 text-right"><?= e(money($g['revenue'])) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php $flushCampusSub(); ?>

    <?php if ($groups): ?>
      <tr class="bg-indigo-600 text-white font-bold">
        <td class="px-3 py-2" colspan="2">GRAND TOTAL</td>
        <?php $gt = 0; foreach ($codes as $c): $gt += $grand['qty'][$c]; ?>
          <td class="px-3 py-2 text-center"><?= $grand['qty'][$c] ?></td>
        <?php endforeach; ?>
        <td class="px-3 py-2 text-center"><?= $gt ?></td>
        <td class="px-3 py-2 text-right"><?= e(money($grand['revenue'])) ?></td>
      </tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<p class="text-xs text-gray-500 mt-2">
  Box columns are quantities. Click a Life Group to see its individual orders.
  <?= $status === 'paid' ? 'Showing paid orders only.' : 'Showing paid orders plus holds that have not expired.' ?>
</p>
</main></body></html>
