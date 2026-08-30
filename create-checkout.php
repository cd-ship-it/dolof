<?php
/**
 * Validate the order form, reserve a timed hold, create a Stripe Checkout
 * Session, and redirect the customer to Stripe. The order is confirmed only
 * when payment completes (stripe-webhook.php / success.php).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
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

$first = trim($_POST['first_name'] ?? '');
$last  = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$selectedCodes = array_values(array_filter((array) ($_POST['boxes'] ?? []), 'is_string'));
$qtyInput      = (array) ($_POST['qty'] ?? []);

$old = [
    'first_name' => $first,
    'last_name'  => $last,
    'email'      => $email,
    'phone'      => $phone,
    'boxes'      => $selectedCodes,
    'qty'        => array_map('intval', $qtyInput),
];

if (!ordering_is_open($pdo)) {
    reject_with(['Online ordering is currently closed.'], $old);
}

$errors = [];
if ($first === '')                                   { $errors[] = 'First name is required.'; }
if ($last === '')                                    { $errors[] = 'Last name is required.'; }
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'A valid email address is required.'; }
if ($phone === '')                                   { $errors[] = 'Phone number is required.'; }
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

if ($errors !== [] || $lines === []) {
    if ($lines === [] && $errors === []) {
        $errors[] = 'Select at least one lunch box.';
    }
    reject_with($errors, $old);
}

// Reserve the hold (race-safe) then open Stripe Checkout.
try {
    $orderId = create_pending_order($pdo, [
        'first_name' => $first,
        'last_name'  => $last,
        'email'      => $email,
        'phone'      => $phone,
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

    $session = \Stripe\Checkout\Session::create([
        'mode'                 => 'payment',
        'line_items'           => $lineItems,
        'customer_email'       => $email,
        'client_reference_id'  => (string) $orderId,
        'metadata'             => ['order_id' => (string) $orderId, 'source' => 'dolos'],
        'payment_intent_data'  => ['metadata' => ['order_id' => (string) $orderId, 'source' => 'dolos']],
        'expires_at'           => time() + STRIPE_CHECKOUT_MINUTES * 60,
        'success_url'          => APP_URL . '/success?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'           => APP_URL . '/cancel?order=' . $orderId,
    ]);

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
