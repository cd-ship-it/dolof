<?php
/**
 * CSV export of paid orders — one row per attendee (registrant + one lunch choice).
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/boxes.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/order_summary.php';

require_admin();

$boxes = get_all_boxes($pdo);
$boxName = array_column($boxes, 'name', 'code');
$boxPrice = array_column($boxes, 'price_cents', 'code');

$orders = $pdo->query(
    "SELECT * FROM " . DOLOS_TBL_ORDERS . " WHERE status = 'paid' ORDER BY created_at, id"
)->fetchAll(PDO::FETCH_ASSOC);

$filename = 'dolos-attendance-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fprintf($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

fputcsv($out, [
    'Order #',
    'Registrant First',
    'Registrant Last',
    'Email',
    'Phone',
    'Campus',
    'Lift Group',
    'Attendee First',
    'Attendee Last',
    'Age Group',
    'Lunch Code',
    'Lunch Name',
    'Lunch Price',
    'Order Total',
    'Placed At',
    'Stripe Session',
    'Flagged',
    'Flag Reason',
]);

foreach ($orders as $o) {
    $base = [
        (int) $o['id'],
        $o['first_name'],
        $o['last_name'],
        $o['email'],
        $o['phone'],
        $o['campus'],
        $o['lift_group'],
    ];
    $tail = [
        number_format($o['total_amount_cents'] / 100, 2, '.', ''),
        $o['created_at'],
        $o['stripe_session_id'],
        ((int) $o['capacity_flag'] === 1) ? 'YES' : '',
        $o['flag_reason'],
    ];

    $people = [];
    foreach (decode_attendees($o['adult_names'] ?? null) as $a) {
        $people[] = ['a' => $a, 'age' => 'Adult'];
    }
    foreach (decode_attendees($o['child_names'] ?? null) as $a) {
        $people[] = ['a' => $a, 'age' => 'Child (12 & under)'];
    }

    if ($people === []) {
        fputcsv($out, array_merge($base, ['', '', '', '', '', ''], $tail));
        continue;
    }

    foreach ($people as $p) {
        $a = $p['a'];
        $code = trim((string) ($a['box'] ?? ''));
        $lunchCode = ($code === '' || $code === 'none') ? 'none' : $code;
        $lunchName = ($lunchCode === 'none')
            ? '留位但不點餐'
            : (string) ($boxName[$lunchCode] ?? $lunchCode);
        $price = ($lunchCode === 'none') ? 0 : (int) ($boxPrice[$lunchCode] ?? 0);
        fputcsv($out, array_merge($base, [
            $a['first'] ?? '',
            $a['last'] ?? '',
            $p['age'],
            $lunchCode,
            $lunchName,
            number_format($price / 100, 2, '.', ''),
        ], $tail));
    }
}

fclose($out);
exit;
