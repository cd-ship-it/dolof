<?php
/**
 * Generate random test orders (Chinese names) and:
 *   1. insert them into the local database, and
 *   2. write a portable sql/seed_test_data.sql you can run against any DB
 *      (e.g. the production copy) that already has the dolos_ tables.
 *
 * Usage:
 *   php scripts/seed-random-orders.php [count]      (default 100)
 *
 * Every seeded order is tagged with stripe_session_id LIKE 'seed_%', so you can
 * wipe just the test data with:
 *   DELETE FROM dolos_orders WHERE stripe_session_id LIKE 'seed_%';
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/boxes.php';

$count = max(1, (int) ($argv[1] ?? 100));

// ── Name pools — Hong Kong / Cantonese romanisation ─────────────────────────
$surnames = ['Chan', 'Lam', 'Wong', 'Cheung', 'Ho', 'Lee', 'Ng', 'Chow', 'Yip', 'Tsang',
    'Lau', 'Leung', 'Mak', 'Tam', 'Kwok', 'Yeung', 'Cheng', 'Fung', 'Wu', 'Chu',
    'Choi', 'Poon', 'So', 'Tang', 'Yuen', 'Kwan', 'Hui', 'Sze', 'Lai', 'Ma',
    'Au', 'Lo', 'To', 'Yau', 'Shum', 'Tsui', 'Kong', 'Fong', 'Ha', 'Yu'];

$givenNames = ['Ka Ho', 'Wai Man', 'Ka Yan', 'Chun Kit', 'Ho Yin', 'Mei Ling', 'Siu Ming',
    'Ka Wai', 'Wing Yan', 'Man Kit', 'Pui Yee', 'Ho Ming', 'Chi Wai', 'Ka Man',
    'Yuk Lan', 'Wai Kit', 'Ka Ying', 'Tsz Kwan', 'Hoi Ching', 'Ho Nam',
    'Wing Sze', 'Ka Lok', 'Chun Yin', 'Sze Wai', 'Yat Long', 'Cheuk Yiu',
    'Hei Man', 'Ching Yee', 'Wan Lok', 'Tin Yau', 'Chi Kin', 'Mei Yee',
    'Wai Lun', 'Ka Chun', 'Suk Yee', 'Ho Kwan', 'Yee Man', 'Chun Hei'];

$areaCodes = ['510', '408', '925', '650', '415', '669'];

// campus → life groups (single source: data/life-groups.json)
$lifeGroups = life_groups_by_campus();
$campuses   = campuses();
if ($campuses === []) {
    fwrite(STDERR, "No campuses in data/life-groups.json — define campuses before seeding.\n");
    exit(1);
}

$boxes = $pdo->query('SELECT id, code, name, price_cents FROM ' . DOLOS_TBL_BOXES . ' ORDER BY sort_order, code')
             ->fetchAll(PDO::FETCH_ASSOC);
if (!$boxes) {
    fwrite(STDERR, "No rows in dolos_boxes — run sql/dev_bootstrap.sql first.\n");
    exit(1);
}
$boxByCode = [];
foreach ($boxes as $b) {
    $boxByCode[$b['code']] = $b;
}
$codes = array_keys($boxByCode);

// ── Build the random dataset once (used for both DB insert and .sql) ─────────
mt_srand(20260830);
$statusPlan = array_merge(
    array_fill(0, 72, 'paid'),
    array_fill(0, 14, 'pending'),
    array_fill(0, 9,  'expired'),
    array_fill(0, 5,  'cancelled')
);

$orders = [];
for ($i = 1; $i <= $count; $i++) {
    $first = $givenNames[array_rand($givenNames)];
    $last  = $surnames[array_rand($surnames)];
    $slug  = strtolower(str_replace(' ', '', $first) . $last);
    $email = $slug . mt_rand(1, 999) . '@example.com';

    $phone = '';
    if (mt_rand(1, 100) <= 70) {
        $phone = sprintf('(%s) %03d-%04d', $areaCodes[array_rand($areaCodes)], mt_rand(200, 999), mt_rand(0, 9999));
    }

    $campus = $campuses[array_rand($campuses)];
    $groupList = $lifeGroups[$campus] ?? [];
    $liftGroup = ($groupList && mt_rand(1, 100) <= 82) ? $groupList[array_rand($groupList)] : '';

    // 1–3 distinct boxes, qty 1–4 each
    $pick = (array) array_rand(array_flip($codes), min(count($codes), mt_rand(1, 3)));
    $items = [];
    $boxCount = 0;
    foreach ($pick as $c) {
        $items[$c] = mt_rand(1, 4);
        $boxCount += $items[$c];
    }

    $attAdults   = max(1, min(50, $boxCount + mt_rand(0, 2)));
    $attChildren = mt_rand(0, 3);
    $adultNames  = [];
    $childNames  = [];
    if ($attAdults >= 1) {
        for ($n = 0; $n < $attAdults; $n++) {
            $adultNames[] = $givenNames[array_rand($givenNames)] . ' ' . $surnames[array_rand($surnames)];
        }
    }
    if ($attChildren >= 1) {
        for ($n = 0; $n < $attChildren; $n++) {
            $childNames[] = $givenNames[array_rand($givenNames)] . ' ' . $surnames[array_rand($surnames)];
        }
    }

    $status = $statusPlan[($i - 1) % count($statusPlan)];
    $createdAt = date('Y-m-d H:i:s', time() - mt_rand(0, 14 * 86400));

    $orders[] = [
        'n'                  => $i,
        'first'              => $first,
        'last'               => $last,
        'email'              => $email,
        'phone'              => $phone,
        'campus'             => $campus,
        'lift_group'         => $liftGroup,
        'attending_adults'   => $attAdults,
        'attending_children' => $attChildren,
        'adult_names'        => $adultNames,
        'child_names'        => $childNames,
        'status'             => $status,
        'items'              => $items,            // code => qty
        'created_at'         => $createdAt,
    ];
}

// ── Insert into the local database ─────────────────────────────────────────
$pdo->beginTransaction();
$pdo->exec("DELETE FROM " . DOLOS_TBL_ORDERS . " WHERE stripe_session_id LIKE 'seed_%'");

$ordStmt = $pdo->prepare(
    'INSERT INTO ' . DOLOS_TBL_ORDERS . '
        (first_name, last_name, email, phone, campus, lift_group,
         attending_adults, attending_children, adult_names, child_names, status,
         total_amount_cents, stripe_session_id, payment_method, hold_expires_at,
         confirmation_email_sent, created_at, updated_at)
     VALUES (:first, :last, :email, :phone, :campus, :lg, :att_adults, :att_children,
             :adult_names, :child_names, :status,
             :total, :sid, \'stripe\',
             :hold, :emailed, :created, :updated)'
);
$itemStmt = $pdo->prepare(
    'INSERT INTO ' . DOLOS_TBL_ITEMS . '
        (order_id, box_id, box_code, box_name, unit_price_cents, quantity)
     VALUES (?, ?, ?, ?, ?, ?)'
);

$inserted = 0;
foreach ($orders as $o) {
    $total = 0;
    foreach ($o['items'] as $c => $q) {
        $total += (int) $boxByCode[$c]['price_cents'] * $q;
    }
    $ordStmt->execute([
        ':first'      => $o['first'],
        ':last'       => $o['last'],
        ':email'      => $o['email'],
        ':phone'      => $o['phone'],
        ':campus'     => $o['campus'],
        ':lg'         => $o['lift_group'],
        ':att_adults'   => $o['attending_adults'],
        ':att_children' => $o['attending_children'],
        ':adult_names'  => encode_attendee_names($o['adult_names']),
        ':child_names'  => encode_attendee_names($o['child_names']),
        ':status'       => $o['status'],
        ':total'      => $total,
        ':sid'        => 'seed_' . $o['n'],
        ':hold'       => $o['status'] === 'pending' ? date('Y-m-d H:i:s', time() + 1800) : null,
        ':emailed'    => $o['status'] === 'paid' ? 1 : 0,
        ':created'    => $o['created_at'],
        ':updated'    => $o['created_at'],
    ]);
    $oid = (int) $pdo->lastInsertId();
    foreach ($o['items'] as $c => $q) {
        $b = $boxByCode[$c];
        $itemStmt->execute([$oid, $b['id'], $c, $b['name'], $b['price_cents'], $q]);
    }
    $inserted++;
}
$pdo->commit();

// ── Write the portable SQL file ───────────────────────────────────────────
$q = fn($s) => "'" . str_replace("'", "''", (string) $s) . "'";

$sql = [];
$sql[] = '-- ============================================================================';
$sql[] = '-- Dolos — RANDOM TEST ORDERS  (' . $count . ' orders, generated ' . date('Y-m-d H:i') . ')';
$sql[] = '-- Portable: run against any database that already has the dolos_ tables';
$sql[] = '-- (production copy included). Box id / name / price are read from that';
$sql[] = "-- database's own dolos_boxes, so it adapts to the live menu.";
$sql[] = '--';
$sql[] = "-- Remove this test data later with:";
$sql[] = "--   DELETE FROM dolos_orders WHERE stripe_session_id LIKE 'seed_%';";
$sql[] = '-- ============================================================================';
$sql[] = '';
$sql[] = 'SET NAMES utf8mb4;';
$sql[] = 'START TRANSACTION;';
$sql[] = '';
$sql[] = "DELETE FROM dolos_orders WHERE stripe_session_id LIKE 'seed_%';";
$sql[] = '';

foreach ($orders as $o) {
    $hold    = $o['status'] === 'pending' ? 'DATE_ADD(NOW(), INTERVAL 30 MINUTE)' : 'NULL';
    $emailed = $o['status'] === 'paid' ? '1' : '0';
    $sql[] = sprintf(
        "INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,attending_adults,attending_children,adult_names,child_names,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)\n"
        . "VALUES (%s,%s,%s,%s,%s,%s,%d,%d,%s,%s,%s,0,%s,'stripe',%s,%s,%s,%s);",
        $q($o['first']), $q($o['last']), $q($o['email']), $q($o['phone']),
        $q($o['campus']), $q($o['lift_group']),
        (int) $o['attending_adults'], (int) $o['attending_children'],
        $q(encode_attendee_names($o['adult_names'])),
        $q(encode_attendee_names($o['child_names'])),
        $q($o['status']),
        $q('seed_' . $o['n']), $hold, $emailed, $q($o['created_at']), $q($o['created_at'])
    );
    $sql[] = 'SET @oid = LAST_INSERT_ID();';
    foreach ($o['items'] as $c => $qty) {
        $sql[] = sprintf(
            "INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)\n"
            . "  SELECT @oid, id, code, name, price_cents, %d FROM dolos_boxes WHERE code=%s;",
            $qty, $q($c)
        );
    }
    $sql[] = '';
}

$sql[] = '-- Recompute each order total from its items (uses the target DB\'s prices).';
$sql[] = "UPDATE dolos_orders o";
$sql[] = "SET total_amount_cents = (SELECT COALESCE(SUM(unit_price_cents*quantity),0)";
$sql[] = "                            FROM dolos_order_items i WHERE i.order_id = o.id)";
$sql[] = "WHERE o.stripe_session_id LIKE 'seed_%';";
$sql[] = '';
$sql[] = 'COMMIT;';
$sql[] = '';

$outPath = dirname(__DIR__) . '/sql/seed_test_data.sql';
file_put_contents($outPath, implode("\n", $sql));

// ── Summary ───────────────────────────────────────────────────────────────
$byStatus = [];
foreach ($orders as $o) {
    $byStatus[$o['status']] = ($byStatus[$o['status']] ?? 0) + 1;
}
echo "Inserted {$inserted} test orders into " . DB_NAME . " (local).\n";
echo "Status mix: " . json_encode($byStatus) . "\n";
echo "Wrote " . $outPath . "\n";
echo "Run it elsewhere with:  mysql -h HOST -u USER -p DBNAME < sql/seed_test_data.sql\n";
