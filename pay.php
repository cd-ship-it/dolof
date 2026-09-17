<?php
/**
 * On-site payment page (Stripe Payment Element + Checkout Sessions ui_mode=custom).
 * Reached after create-checkout.php when checkout_mode=elements.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/boxes.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/layout.php';

auth_start_session();

$orderId = (int) ($_GET['order'] ?? 0);
$paySession = $_SESSION['stripe_pay'] ?? null;

function pay_fail_redirect(string $msg = ''): void
{
    if ($msg !== '') {
        $_SESSION['flash_error'] = $msg;
    }
    header('Location: ' . APP_URL . '/order', true, 302);
    exit;
}

if ($orderId <= 0
    || !is_array($paySession)
    || (int) ($paySession['order_id'] ?? 0) !== $orderId
    || trim((string) ($paySession['client_secret'] ?? '')) === ''
) {
    pay_fail_redirect('Your payment session expired. Please place your order again.');
}

$order = order_get_with_items($pdo, $orderId);
if ($order === null || ($order['status'] ?? '') !== 'pending') {
    unset($_SESSION['stripe_pay']);
    pay_fail_redirect('That order is no longer awaiting payment.');
}

$holdExpiresAt = null;
$holdExpiresTs = 0;
if (!empty($order['hold_expires_at'])) {
    try {
        $holdExpiresAt = new DateTimeImmutable($order['hold_expires_at']);
        $holdExpiresTs = $holdExpiresAt->getTimestamp();
    } catch (Exception $e) {
        $holdExpiresAt = null;
    }
}
$holdExpired = $holdExpiresAt !== null && $holdExpiresAt <= new DateTimeImmutable('now');
if ($holdExpired) {
    unset($_SESSION['stripe_pay']);
    pay_fail_redirect('Your seat hold expired before payment. Please place your order again.');
}

$clientSecret = (string) $paySession['client_secret'];
$totalCents  = (int) $order['total_amount_cents'];
$totalLabel  = money($totalCents);
$customerName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
$adultAttendees = decode_attendees($order['adult_names'] ?? null);
$childAttendees = decode_attendees($order['child_names'] ?? null);
$boxByCode = [];
foreach ($order['items'] ?? [] as $it) {
    $code = (string) ($it['box_code'] ?? '');
    if ($code !== '') {
        $boxByCode[$code] = [
            'name'  => (string) ($it['box_name'] ?? $code),
            'price' => (int) ($it['unit_price_cents'] ?? 0),
        ];
    }
}
$lunchRows = [];
foreach (array_merge($adultAttendees, $childAttendees) as $a) {
    $name = attendee_full_name($a);
    $code = trim((string) ($a['box'] ?? ''));
    if ($name === '') {
        continue;
    }
    if ($code === '' || $code === 'none') {
        $lunchRows[] = [
            'name'     => $name,
            'box_html' => e('留位但不點餐'),
            'subtotal' => 0,
        ];
        continue;
    }
    $info = $boxByCode[$code] ?? ['name' => $code, 'price' => 0];
    $lunchRows[] = [
        'name'     => $name,
        'box_html' => '<span class="font-semibold text-indigo-700">' . e($code) . '</span> '
            . dish_name_html($info['name']),
        'subtotal' => (int) $info['price'],
    ];
}
$pubKey = STRIPE_PUBLIC_KEY;

if ($pubKey === '') {
    app_log('high', 'Payment', 'pay page missing publishable key', ['order_id' => $orderId]);
    pay_fail_redirect('Payment is temporarily unavailable. Please try again later.');
}

layout_head('Pay — Deacons Ordination Lunch Ordering Form');
?>
<div class="card mb-6">
  <h1 class="text-2xl font-bold text-indigo-900">Complete payment</h1>
  <p class="text-sm text-gray-600 mt-1">
    Your seats are held while you pay. Order #<?= (int) $orderId ?>.
  </p>
  <?php if ($holdExpiresTs > 0): ?>
  <p class="text-sm text-amber-800 mt-2" id="hold-countdown"
     data-expires="<?= (int) $holdExpiresTs ?>">
    Hold expires in <span id="hold-remaining">…</span>
  </p>
  <?php endif; ?>
</div>

<div class="card mb-6 space-y-3 text-sm">
  <h2 class="text-base font-semibold text-gray-900">Order summary</h2>
  <div class="text-gray-800 space-y-0.5">
    <div>
      <span class="font-bold text-gray-900">Name</span> <?= e($customerName) ?>
      <?php if (trim((string) ($order['phone'] ?? '')) !== ''): ?>
        <span class="text-gray-300 mx-1.5">·</span>
        <span class="font-bold text-gray-900">Phone</span> <?= e($order['phone']) ?>
      <?php endif; ?>
    </div>
    <?php if (trim((string) ($order['email'] ?? '')) !== ''): ?>
      <div><span class="text-gray-500">Email:</span> <?= e($order['email']) ?></div>
    <?php endif; ?>
    <div>
      <span class="font-bold text-gray-900">Campus</span> <?= e($order['campus'] ?? '') ?>
      <span class="text-gray-300 mx-1.5">·</span>
      <span class="font-bold text-gray-900">Lift Group</span> <?= e($order['lift_group'] ?? '') ?>
    </div>
  </div>

  <?php if ($lunchRows !== []): ?>
  <table class="w-full text-sm border-t border-gray-200 pt-2">
    <thead>
      <tr class="text-left text-gray-500">
        <th class="py-1">Name</th>
        <th class="py-1">Lunch box</th>
        <th class="py-1 text-right">Subtotal</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($lunchRows as $row): ?>
      <tr class="border-b border-gray-100">
        <td class="py-2 pr-3 align-top"><?= e($row['name']) ?></td>
        <td class="py-2 pr-3 align-top"><?= $row['box_html'] ?></td>
        <td class="py-2 text-right align-top whitespace-nowrap"><?= e(money((int) $row['subtotal'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td class="pt-3 font-bold text-base" colspan="2">Total due</td>
        <td class="pt-3 font-bold text-base text-right text-indigo-900"><?= e($totalLabel) ?></td>
      </tr>
    </tfoot>
  </table>
  <?php endif; ?>
</div>

<div class="card mb-6 space-y-4">
  <h2 class="text-base font-semibold text-gray-900">Payment</h2>
  <div id="payment-error" class="hidden rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" role="alert"></div>

  <div id="express-checkout-wrap" class="hidden">
    <div id="express-checkout-element"></div>
    <div class="relative my-4 text-center">
      <span class="relative z-10 bg-white px-3 text-xs uppercase tracking-wide text-gray-400">Or pay with card</span>
      <div class="absolute inset-x-0 top-1/2 border-t border-gray-200" aria-hidden="true"></div>
    </div>
  </div>

  <div id="payment-element" class="min-h-[120px]">
    <p class="text-sm text-gray-500" id="payment-loading">Loading secure payment form…</p>
  </div>
  <button type="button" id="pay-submit" class="btn-primary w-full" disabled>
    Pay <?= e($totalLabel) ?>
  </button>
  <p class="text-xs text-gray-500 text-center">
    Secured by Stripe. Card details never touch our servers. Your order is confirmed only after payment succeeds.
  </p>
  <a href="<?= e(APP_URL) ?>/cancel?order=<?= (int) $orderId ?>"
     class="block w-full rounded-md border-2 border-gray-300 px-4 py-2 text-center text-sm font-medium text-gray-700 hover:bg-gray-50"
     id="pay-cancel">
    Cancel order
  </a>
</div>

<script src="https://js.stripe.com/basil/stripe.js"></script>
<script>
(function () {
  var pk = <?= json_encode($pubKey, JSON_UNESCAPED_SLASHES) ?>;
  var clientSecret = <?= json_encode($clientSecret, JSON_UNESCAPED_SLASHES) ?>;
  var totalLabel = <?= json_encode($totalLabel, JSON_UNESCAPED_SLASHES) ?>;
  var holdExpiresTs = <?= (int) $holdExpiresTs ?>;

  var payBtn = document.getElementById('pay-submit');
  var errEl = document.getElementById('payment-error');
  var loadingEl = document.getElementById('payment-loading');
  var holdEl = document.getElementById('hold-remaining');
  var countdownEl = document.getElementById('hold-countdown');
  var submitting = false;
  var canConfirm = false;
  var holdExpired = false;
  var confirmFn = null;

  function showError(msg) {
    if (!errEl) return;
    errEl.textContent = msg || 'Payment failed. Please try again.';
    errEl.classList.remove('hidden');
  }

  function clearError() {
    if (!errEl) return;
    errEl.textContent = '';
    errEl.classList.add('hidden');
  }

  function setPayEnabled() {
    if (!payBtn) return;
    payBtn.disabled = submitting || holdExpired || !canConfirm;
  }

  function tickHold() {
    if (!holdEl || !holdExpiresTs) return;
    var left = holdExpiresTs - Math.floor(Date.now() / 1000);
    if (left <= 0) {
      holdExpired = true;
      holdEl.textContent = 'expired';
      if (countdownEl) countdownEl.classList.add('text-red-700');
      setPayEnabled();
      showError('Your seat hold expired. Please cancel and place your order again.');
      return;
    }
    var m = Math.floor(left / 60);
    var s = left % 60;
    holdEl.textContent = m + ':' + (s < 10 ? '0' : '') + s;
    setTimeout(tickHold, 1000);
  }
  tickHold();

  if (!window.Stripe) {
    showError('Could not load Stripe. Please refresh and try again.');
    if (loadingEl) loadingEl.textContent = 'Payment form unavailable.';
    return;
  }

  var stripe = Stripe(pk);

  async function mountPayment() {
    try {
      if (typeof stripe.initCheckout !== 'function') {
        throw new Error('This Stripe.js build does not support custom Checkout.');
      }

      // Basil Stripe.js requires fetchClientSecret (not clientSecret).
      var checkout = await stripe.initCheckout({
        fetchClientSecret: function () {
          return Promise.resolve(clientSecret);
        }
      });

      if (typeof checkout.on === 'function') {
        checkout.on('change', function (session) {
          canConfirm = !!(session && session.canConfirm);
          setPayEnabled();
        });
      } else {
        canConfirm = true;
      }

      confirmFn = async function () {
        if (typeof checkout.loadActions === 'function') {
          var loaded = await checkout.loadActions();
          if (loaded && loaded.type === 'error') {
            return { type: 'error', error: loaded.error || { message: 'Could not prepare payment.' } };
          }
          var actions = loaded && loaded.actions ? loaded.actions : null;
          if (!actions || typeof actions.confirm !== 'function') {
            return { type: 'error', error: { message: 'Payment confirm is unavailable.' } };
          }
          return await actions.confirm();
        }
        if (typeof checkout.confirm === 'function') {
          return await checkout.confirm();
        }
        return { type: 'error', error: { message: 'Payment confirm is unavailable.' } };
      };

      // Apple Pay / Google Pay buttons on top (when device + Dashboard allow them).
      var expressWrap = document.getElementById('express-checkout-wrap');
      if (typeof checkout.createExpressCheckoutElement === 'function' && expressWrap) {
        var expressCheckoutElement = checkout.createExpressCheckoutElement({
          paymentMethods: {
            applePay: 'always',
            googlePay: 'always',
            link: 'auto',
            paypal: 'never',
            amazonPay: 'never',
            klarna: 'never'
          }
        });
        expressCheckoutElement.mount('#express-checkout-element');
        expressCheckoutElement.on('ready', function (event) {
          var methods = event && event.availablePaymentMethods;
          if (methods && (methods.applePay || methods.googlePay || methods.link)) {
            expressWrap.classList.remove('hidden');
          }
        });
        expressCheckoutElement.on('confirm', async function () {
          if (submitting || holdExpired || !confirmFn) return;
          clearError();
          submitting = true;
          setPayEnabled();
          try {
            var result = await confirmFn();
            if (result && result.type === 'error') {
              showError((result.error && result.error.message) || 'Payment was not completed.');
            } else if (result && result.error) {
              showError(result.error.message || 'Payment was not completed.');
            }
          } catch (e) {
            showError((e && e.message) ? e.message : 'Payment failed. Please try again.');
          }
          submitting = false;
          setPayEnabled();
        });
      }

      // Card form below; hide wallet duplicates already shown in Express Checkout.
      var paymentElement = checkout.createPaymentElement({
        wallets: {
          applePay: 'never',
          googlePay: 'never'
        }
      });
      paymentElement.mount('#payment-element');

      if (loadingEl) loadingEl.remove();
      setPayEnabled();
    } catch (e) {
      if (loadingEl) loadingEl.textContent = 'Payment form unavailable.';
      showError((e && e.message) ? e.message : 'Could not start the payment form.');
    }
  }

  payBtn.addEventListener('click', async function () {
    if (submitting || holdExpired || !confirmFn) return;
    clearError();
    submitting = true;
    payBtn.textContent = 'Processing…';
    setPayEnabled();
    try {
      var result = await confirmFn();
      if (result && result.type === 'error') {
        showError((result.error && result.error.message) || 'Payment was not completed.');
      } else if (result && result.error) {
        showError(result.error.message || 'Payment was not completed.');
      }
    } catch (e) {
      showError((e && e.message) ? e.message : 'Payment failed. Please try again.');
    }
    submitting = false;
    payBtn.textContent = 'Pay ' + totalLabel;
    setPayEnabled();
  });

  mountPayment();
})();
</script>
<?php layout_footer(); ?>
