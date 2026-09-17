<?php
/**
 * Validate the order form, reserve a timed hold, create a Stripe Checkout
 * Session, then either redirect to hosted Checkout or to /pay (Elements)
 * depending on checkout_mode. The order is confirmed only when payment
 * completes (stripe-webhook.php / success.php).
 *
 * Free RSVP ($0, all Not Ordering) skips Stripe and confirms immediately.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/boxes.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/vendor/autoload.php';

auth_start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/order', true, 302);
    exit;
}
csrf_verify();

/** Re-render the form with errors and the user's input. */
function reject_with(array $errors, array $old): void
{
    global $pdo;
    $form_errors = $errors;
    require __DIR__ . '/order.php';
    exit;
}

$first     = trim($_POST['first_name'] ?? '');
$last      = trim($_POST['last_name'] ?? '');
$email     = trim($_POST['email'] ?? '');
$phone     = trim($_POST['phone'] ?? '');
$campus    = trim($_POST['campus'] ?? '');
$liftGroup = trim($_POST['lift_group'] ?? '');
$attendees = posted_form_attendees(50);
$adultAttendees = [];
$childAttendees = [];
foreach ($attendees as $a) {
    $row = [
        'first' => $a['first'],
        'last'  => $a['last'],
        'box'   => $a['box'],
    ];
    if ($a['child']) {
        $childAttendees[] = $row;
    } else {
        $adultAttendees[] = $row;
    }
}
$attAdults   = count($adultAttendees);
$attChildren = count($childAttendees);

$campuses = campuses();

$old = [
    'first_name'         => $first,
    'last_name'          => $last,
    'email'              => $email,
    'phone'              => $phone,
    'campus'             => $campus,
    'lift_group'         => $liftGroup,
    'attending_adults'   => $attAdults,
    'attending_children' => $attChildren,
    'attendees'          => $attendees,
    'adult_attendees'    => $adultAttendees,
    'child_attendees'    => $childAttendees,
];

if (!ordering_accepts_checkout($pdo)) {
    if (!ordering_is_open($pdo)) {
        reject_with(['Online ordering is currently closed.'], $old);
    }
    $start = ordering_window_start();
    $end   = ordering_window_end();
    $now   = new DateTimeImmutable('now');
    if ($start && $now < $start) {
        reject_with(['Ordering has not opened yet. It starts ' . ordering_format_pt($start) . '.'], $old);
    }
    if ($end && $now > $end) {
        reject_with(['Ordering has closed. The window ended ' . ordering_format_pt($end) . '.'], $old);
    }
    reject_with(['Online ordering is not available right now.'], $old);
}

if ($campuses === []) {
    reject_with(['Campus options are not configured. Please contact the church office.'], $old);
}

$errors = [];
if ($first === '')                                   { $errors[] = 'First name is required.'; }
if ($last === '')                                    { $errors[] = 'Last name is required.'; }
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email address is not valid.';
}
$emailOk = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
$phoneOk = strlen(preg_replace('/\D+/', '', $phone)) >= 10;
if (!$emailOk && !$phoneOk) {
    $errors[] = 'Please provide either a valid email or a phone number.';
}
if (!in_array($campus, $campuses, true))             { $errors[] = 'Please choose a campus.'; }
if ($liftGroup === '')                               { $errors[] = 'Lift Group Name is required.'; }
if (mb_strlen($liftGroup) > 20)                      { $errors[] = 'Lift Group Name must be 20 characters or fewer.'; }
if ($attAdults + $attChildren < 1)                   { $errors[] = 'Please add at least one person attending.'; }
if ($attAdults + $attChildren > 50)                  { $errors[] = 'Attendance cannot exceed 50 people.'; }
if ($attChildren > 0 && $attAdults < 1)              { $errors[] = 'At least one person who is not 12 or under is required.'; }

$activeByCode = [];
foreach (get_active_boxes($pdo) as $b) {
    $activeByCode[$b['code']] = $b;
}
$validBoxCodes = array_keys($activeByCode);
$validBoxCodes[] = 'none';

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

// Aggregate per-person box choices into order lines.
$qtyByCode = [];
foreach (array_merge($adultAttendees, $childAttendees) as $a) {
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
    reject_with($errors, $old);
}

$customer = [
    'first_name'         => $first,
    'last_name'          => $last,
    'email'              => $email,
    'phone'              => $phone,
    'campus'             => $campus,
    'lift_group'         => $liftGroup,
    'attending_adults'   => $attAdults,
    'attending_children' => $attChildren,
    'adult_attendees'    => $adultAttendees,
    'child_attendees'    => $childAttendees,
];

// Free RSVP — attendance only, no lunch boxes.
if ($lines === []) {
    try {
        $orderId = create_rsvp_order($pdo, $customer);
        $order = order_get_with_items($pdo, $orderId);
        if ($order !== null) {
            send_order_confirmation_email($pdo, $order);
            $pdo->prepare(
                'UPDATE ' . DOLOS_TBL_ORDERS . ' SET confirmation_email_sent = 1, updated_at = NOW() WHERE id = ?'
            )->execute([$orderId]);
        }
        $_SESSION['rsvp_order_id'] = $orderId;
        header('Location: ' . APP_URL . '/success?rsvp=1', true, 303);
        exit;
    } catch (Throwable $e) {
        app_log('high', 'Order', 'rsvp create failed', ['error' => $e->getMessage()]);
        reject_with(['Something went wrong saving your RSVP. Please try again.'], $old);
    }
}

// Paid order — reserve hold then Stripe Checkout.
try {
    $orderId = create_pending_order($pdo, $customer, $lines, HOLD_MINUTES);
} catch (BoxCapacityException $e) {
    app_log('high', 'Order', 'capacity rejection', ['sold_out' => $e->getSoldOutCodes()]);
    reject_with($e->getErrors(), $old);
} catch (Throwable $e) {
    app_log('high', 'Order', 'create failed', ['error' => $e->getMessage()]);
    reject_with(['Something went wrong creating your order. Please try again.'], $old);
}

try {
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

    $lineItems = [];
    foreach ($lines as $line) {
        $lineItems[] = [
            'quantity'   => $line['quantity'],
            'price_data' => [
                'currency'     => 'usd',
                'unit_amount'  => $line['unit_price_cents'],
                'product_data' => ['name' => $line['name'] . ' (' . $line['code'] . ')'],
            ],
        ];
    }

    $useElements = checkout_mode_is_elements($pdo);

    $sessionParams = [
        'mode'                 => 'payment',
        'line_items'           => $lineItems,
        'client_reference_id'  => (string) $orderId,
        'metadata'             => ['order_id' => (string) $orderId, 'source' => 'dolos', 'campus' => $campus, 'lift_group' => $liftGroup],
        'payment_intent_data'  => ['metadata' => ['order_id' => (string) $orderId, 'source' => 'dolos']],
        'expires_at'           => time() + STRIPE_CHECKOUT_MINUTES * 60,
    ];
    if ($emailOk) {
        $sessionParams['customer_email'] = $email;
    }

    if ($useElements) {
        // On-site Payment Element (Checkout Sessions ui_mode=custom).
        $sessionParams['ui_mode'] = 'custom';
        $sessionParams['return_url'] = APP_URL . '/success?session_id={CHECKOUT_SESSION_ID}';
        $sessionParams['billing_address_collection'] = 'auto';
    } else {
        $sessionParams['success_url'] = APP_URL . '/success?session_id={CHECKOUT_SESSION_ID}';
        $sessionParams['cancel_url']  = APP_URL . '/cancel?order=' . $orderId;
    }

    $session = \Stripe\Checkout\Session::create($sessionParams);

    order_attach_stripe_session($pdo, $orderId, $session->id);

    app_log('high', 'Payment', 'checkout session created', [
        'order_id' => $orderId,
        'stripe_session_id' => $session->id,
        'checkout_mode' => $useElements ? 'elements' : 'hosted',
    ]);

    if ($useElements) {
        $clientSecret = (string) ($session->client_secret ?? '');
        if ($clientSecret === '') {
            throw new RuntimeException('Checkout session missing client_secret for elements mode');
        }
        $_SESSION['stripe_pay'] = [
            'order_id'      => $orderId,
            'client_secret' => $clientSecret,
            'session_id'    => $session->id,
        ];
        header('Location: ' . APP_URL . '/pay?order=' . $orderId, true, 303);
        exit;
    }

    header('Location: ' . $session->url, true, 303);
    exit;
} catch (Throwable $e) {
    app_log('high', 'Payment', 'checkout session failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);
    mark_order_cancelled($pdo, $orderId);
    reject_with(['We could not start the payment process. Please try again.'], $old);
}
