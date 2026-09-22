<?php
/**
 * Validate + record a staff order — no payment, but box capacity is
 * enforced exactly like create-checkout.php (create_staff_order() locks and
 * re-checks capacity the same way create_pending_order() does). A token from
 * staff_order.php gates entry and is atomically consumed alongside the order
 * insert (see create_staff_order() in includes/orders.php), and a
 * confirmation email is sent with "no payment required" wording.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/boxes.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/staff_tokens.php';

auth_start_session();
$_GET['lang'] = 'en'; // staff pages are English-only, no language selector

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/order', true, 302);
    exit;
}
csrf_verify();

function staff_link_invalid(): void
{
    layout_head('Staff Order');
    ?>
    <div class="card border-red-300 bg-red-50 text-red-800">
      <h1 class="text-xl font-bold mb-2">This link is no longer valid</h1>
      <p>This ordering link is invalid or has already been used. Each staff link can only be
        used once. If you believe this is a mistake, contact the church office at
        <a href="mailto:cd@crosspointchurchsv.org" class="underline">cd@crosspointchurchsv.org</a>.</p>
    </div>
    <?php
    layout_footer();
    exit;
}

/** Re-render the form with errors and the user's input (mirrors create-checkout.php). */
function reject_with(array $errors, array $old, array $staffPerson, string $token): void
{
    global $pdo;
    $form_errors = $errors;
    $staffMode   = true;
    $staffToken  = $token;
    require __DIR__ . '/order.php';
    exit;
}

$key = trim((string) ($_POST['staff_token'] ?? ''));
$staffPersonForRender = staff_token_lookup($pdo, $key);
if ($staffPersonForRender === null || $staffPersonForRender['used_at'] !== null) {
    staff_link_invalid();
}

$first     = trim($_POST['first_name'] ?? '');
$last      = trim($_POST['last_name'] ?? '');
$email     = trim($_POST['email'] ?? '');
$phone     = trim($_POST['phone'] ?? '');
$campus    = trim($_POST['campus'] ?? '');
$liftGroup = trim($_POST['lift_group'] ?? '');
$attendees = posted_form_attendees(50);
$adultAttendees = [];
foreach ($attendees as $a) {
    $adultAttendees[] = ['first' => $a['first'], 'last' => $a['last'], 'box' => $a['box']];
}
$attAdults = count($adultAttendees);

$old = [
    'first_name'         => $first,
    'last_name'          => $last,
    'email'              => $email,
    'phone'              => $phone,
    'campus'             => $campus,
    'lift_group'         => $liftGroup,
    'attending_adults'   => $attAdults,
    'attending_children' => 0,
    'attendees'          => $attendees,
    'adult_attendees'    => $adultAttendees,
    'child_attendees'    => [],
];

// Campus/Life Group are optional for staff — seat with a real campus/life
// group when picked, otherwise fall back to the generic staff group.
if ($campus === '' || !in_array($campus, campuses(), true)) {
    $finalCampus    = 'Crosspoint';
    $finalLiftGroup = 'Staff';
} else {
    $finalCampus    = $campus;
    $finalLiftGroup = $liftGroup !== '' ? mb_substr($liftGroup, 0, 20) : 'Staff';
}

$errors = [];
if ($first === '') { $errors[] = 'First name is required.'; }
if ($last === '')  { $errors[] = 'Last name is required.'; }
if ($attAdults < 1)  { $errors[] = 'Please add at least one person attending.'; }
if ($attAdults > 50) { $errors[] = 'Attendance cannot exceed 50 people.'; }

$activeByCode = [];
foreach (get_active_boxes($pdo) as $b) {
    $activeByCode[$b['code']] = $b;
}
$validBoxCodes = array_keys($activeByCode);
if (SHOW_NOT_ORDERING) {
    $validBoxCodes[] = 'none';
}

foreach ($attendees as $i => $a) {
    $n = $i + 1;
    if ($a['first'] === '') {
        $errors[] = 'Please enter a first name for attendance ' . $n . '.';
    } elseif (mb_strlen($a['first']) > 100) {
        $errors[] = 'Attendance ' . $n . ' first name must be 100 characters or fewer.';
    }
    if ($a['last'] === '') {
        $errors[] = 'Please enter a last name for attendance ' . $n . '.';
    } elseif (mb_strlen($a['last']) > 100) {
        $errors[] = 'Attendance ' . $n . ' last name must be 100 characters or fewer.';
    }
    if ($a['box'] === '' || !in_array($a['box'], $validBoxCodes, true)) {
        $errors[] = 'Please choose a lunch option for attendance ' . $n . '.';
    }
}

$qtyByCode = [];
foreach ($adultAttendees as $a) {
    if ($a['box'] !== '' && $a['box'] !== 'none') {
        $qtyByCode[$a['box']] = ($qtyByCode[$a['box']] ?? 0) + 1;
    }
}

$lines = [];
foreach ($qtyByCode as $code => $qty) {
    if (!isset($activeByCode[$code])) {
        $errors[] = 'An unavailable lunch box was selected.';
        continue;
    }
    $box = $activeByCode[$code];
    $lines[] = [
        'box_id'           => (int) $box['id'],
        'code'             => $box['code'],
        'name'             => $box['name'],
        'unit_price_cents' => (int) $box['price_cents'],
        'quantity'         => $qty,
    ];
}

if ($errors !== []) {
    reject_with($errors, $old, $staffPersonForRender, $key);
}

$customer = [
    'first_name'         => $first,
    'last_name'          => $last,
    'email'              => $email,
    'phone'              => $phone,
    'campus'             => $finalCampus,
    'lift_group'         => $finalLiftGroup,
    'attending_adults'   => $attAdults,
    'attending_children' => 0,
    'adult_attendees'    => $adultAttendees,
    'child_attendees'    => [],
];

try {
    $orderId = create_staff_order($pdo, $customer, $lines, $key);
} catch (StaffTokenUsedException $e) {
    staff_link_invalid();
} catch (BoxCapacityException $e) {
    app_log('high', 'Order', 'staff order capacity rejection', ['sold_out' => $e->getSoldOutCodes()]);
    reject_with($e->getErrors(), $old, $staffPersonForRender, $key);
} catch (Throwable $e) {
    app_log('high', 'Order', 'staff order create failed', ['error' => $e->getMessage()]);
    reject_with(['Something went wrong saving your order. Please try again.'], $old, $staffPersonForRender, $key);
}

$order = order_get_with_items($pdo, $orderId);
if ($order !== null) {
    send_staff_order_confirmation_email($pdo, $order);
    $pdo->prepare(
        'UPDATE ' . DOLOS_TBL_ORDERS . ' SET confirmation_email_sent = 1, updated_at = NOW() WHERE id = ?'
    )->execute([$orderId]);
}

layout_head('Order Submitted');
?>
<div class="card border-emerald-300 bg-emerald-50 text-emerald-900">
  <h1 class="text-xl font-bold mb-2">Thank you!</h1>
  <p>Your staff lunch box order has been recorded — no payment is required. A confirmation
    email has been sent to <?= e($email) ?>.</p>
  <p class="mt-2">You can close this window now.</p>
</div>
<?php
layout_footer();
