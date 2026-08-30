<?php
/**
 * CSV export of paid orders — one row per order, a quantity column per box.
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/boxes.php';

require_admin();

$boxes = get_all_boxes($pdo);
$codes = array_column($boxes, 'code');

$orders = $pdo->query(
    "SELECT * FROM " . DOLOS_TBL_ORDERS . " WHERE status = 'paid' ORDER BY created_at"
)->fetchAll(PDO::FETCH_ASSOC);

$itemStmt = $pdo->query(
    "SELECT oi.order_id, oi.box_code, oi.quantity
       FROM " . DOLOS_TBL_ITEMS . " oi
       JOIN " . DOLOS_TBL_ORDERS . " o ON o.id = oi.order_id
      WHERE o.status = 'paid'"
)->fetchAll(PDO::FETCH_ASSOC);

$qtyByOrder = [];
foreach ($itemStmt as $r) {
    $qtyByOrder[(int) $r['order_id']][$r['box_code']] = (int) $r['quantity'];
}

$filename = 'dolos-orders-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fprintf($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

$header = ['Order #', 'First Name', 'Last Name', 'Email', 'Phone'];
foreach ($codes as $c) {
    $header[] = 'Box ' . $c;
}
$header = array_merge($header, ['Total Paid', 'Placed At', 'Stripe Session', 'Flagged', 'Flag Reason']);
fputcsv($out, $header);

foreach ($orders as $o) {
    $row = [
        (int) $o['id'],
        $o['first_name'],
        $o['last_name'],
        $o['email'],
        $o['phone'],
    ];
    foreach ($codes as $c) {
        $row[] = $qtyByOrder[(int) $o['id']][$c] ?? 0;
    }
    $row[] = number_format($o['total_amount_cents'] / 100, 2, '.', '');
    $row[] = $o['created_at'];
    $row[] = $o['stripe_session_id'];
    $row[] = ((int) $o['capacity_flag'] === 1) ? 'YES' : '';
    $row[] = $o['flag_reason'];
    fputcsv($out, $row);
}

fclose($out);
exit;
