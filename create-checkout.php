<?php
/**
 * Validate the order form, reserve a timed hold, create a Stripe Checkout
 * Session, and redirect the customer to Stripe. The order is confirmed only
 * when payment completes (stripe-webhook.php / success.php).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/boxes.php';
require_once __DIR__ . '/includes/orders.php';
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
$attAdults   = (int) ($_POST['attending_adults'] ?? 0);
$attChildren = (int) ($_POST['attending_children'] ?? 0);
$adultNames  = normalize_attendee_names($_POST['adult_names'] ?? [], $attAdults >= 1 ? $attAdults : 0);
$childNames  = normalize_attendee_names($_POST['child_names'] ?? [], $attChildren >= 1 ? $attChildren : 0);
$selectedCodes = array_values(array_filter((array) ($_POST['boxes'] ?? []), 'is_string'));
$qtyInput      = (array) ($_POST['qty'] ?? []);

$campuses = campuses();

$old = [
    'first_name'          => $first,
    'last_name'           => $last,
    'email'               => $email,
    'phone'               => $phone,
    'campus'              => $campus,
    'lift_group'          => $liftGroup,
    'attending_adults'    => $attAdults,
    'attending_children'  => $attChildren,
    'adult_names'         => $adultNames,
    'child_names'         => $childNames,
    'boxes'               => $selectedCodes,
    'qty'                 => array_map('intval', $qtyInput),
];

if (!ordering_is_open($pdo)) {
    reject_with(['Online ordering is currently closed.'], $old);
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
if ($attAdults < 0 || $attAdults > 50)               { $errors[] = 'Adult count must be between 0 and 50.'; }
if ($attChildren < 0 || $attChildren > 50)           { $errors[] = 'Children (Age 12 and below) must be between 0 and 50.'; }
if ($attAdults + $attChildren < 1)                   { $errors[] = 'Please enter at least one adult or child attending.'; }
if ($attChildren > 0 && $attAdults < 1)              { $errors[] = 'At least one adult is required when children are attending.'; }
if ($attAdults >= 1) {
    foreach ($adultNames as $i => $n) {
        if ($n === '') {
            $errors[] = 'Please enter a name for Adult ' . ($i + 1) . '.';
        } elseif (mb_strlen($n) > 100) {
            $errors[] = 'Adult ' . ($i + 1) . ' name must be 100 characters or fewer.';
        }
    }
}
if ($attChildren >= 1) {
    foreach ($childNames as $i => $n) {
        if ($n === '') {
            $errors[] = 'Please enter a name for Child ' . ($i + 1) . '.';
        } elseif (mb_strlen($n) > 100) {
            $errors[] = 'Child ' . ($i + 1) . ' name must be 100 characters or fewer.';
        }
    }
}
if ($selectedCodes === [])                           { $errors[] = 'Select at least one lunch box.'; }

// Match selections to active boxes and build order lines.
$activeByCode = [];
foreach (get_active_boxes($pdo) as $b) {
    $activeByCode[$b['code']] = $b;
}

$lines = [];
foreach ($selectedCodes as $code) {
    if (!isset($activeByCode[$code])) {
        $errors[] = 'An unavailable lunch box was selected.';
        continue;
    }
    $box = $activeByCode[$code];
    $qty = (int) ($qtyInput[$code] ?? 0);
    if ($qty < 1 || $qty > DOLOS_MAX_QTY_PER_BOX) {
        $errors[] = sprintf('Quantity for %s must be between 1 and %d.', $box['name'], DOLOS_MAX_QTY_PER_BOX);
        continue;
    }
    $lines[] = [
        'box_id'           => (int) $box['id'],
        'code'             => $box['code'],
        'name'             => $box['name'],
        'unit_price_cents' => (int) $box['price_cents'],
        'quantity'         => $qty,
    ];
}

$boxTotal = 0;
foreach ($lines as $line) {
    $boxTotal += (int) $line['quantity'];
}
if ($lines !== [] && $boxTotal > ($attAdults + $attChildren)) {
    $errors[] = 'Total lunch boxes cannot exceed total attendance (max one box per person).';
}

if ($errors !== [] || $lines === []) {
    if ($lines === [] && $errors === []) {
        $errors[] = 'Select at least one lunch box.';
    }
    reject_with($errors, $old);
}

// Reserve the hold (race-safe) then open Stripe Checkout.
try {
    $orderId = create_pending_order($pdo, [
        'first_name'      => $first,
        'last_name'       => $last,
        'email'           => $email,
        'phone'           => $phone,
        'campus'          => $campus,
        'lift_group'      => $liftGroup,
        'attending_adults'   => $attAdults,
        'attending_children' => $attChildren,
        'adult_names'        => $adultNames,
        'child_names'        => $childNames,
    ], $lines, HOLD_MINUTES);
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

    $sessionParams = [
        'mode'                 => 'payment',
        'line_items'           => $lineItems,
        'client_reference_id'  => (string) $orderId,
        'metadata'             => ['order_id' => (string) $orderId, 'source' => 'dolos', 'campus' => $campus, 'lift_group' => $liftGroup],
        'payment_intent_data'  => ['metadata' => ['order_id' => (string) $orderId, 'source' => 'dolos']],
        'expires_at'           => time() + STRIPE_CHECKOUT_MINUTES * 60,
        'success_url'          => APP_URL . '/success?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'           => APP_URL . '/cancel?order=' . $orderId,
    ];
    if ($emailOk) {
        $sessionParams['customer_email'] = $email;
    }

    $session = \Stripe\Checkout\Session::create($sessionParams);

    order_attach_stripe_session($pdo, $orderId, $session->id);

    app_log('high', 'Payment', 'checkout session created', [
        'order_id' => $orderId, 'stripe_session_id' => $session->id,
    ]);

    header('Location: ' . $session->url, true, 303);
    exit;
} catch (Throwable $e) {
    app_log('high', 'Payment', 'checkout session failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);
    mark_order_cancelled($pdo, $orderId); // release the hold immediately
    reject_with(['We could not start the payment process. Please try again.'], $old);
}
