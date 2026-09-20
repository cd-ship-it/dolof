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
require_once __DIR__ . '/includes/order_summary.php';
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
$pubKey = STRIPE_PUBLIC_KEY;

if ($pubKey === '') {
    app_log('high', 'Payment', 'pay page missing publishable key', ['order_id' => $orderId]);
    pay_fail_redirect('Payment is temporarily unavailable. Please try again later.');
}

layout_head('Pay');
?>
<div class="card mb-6">
  <h1 class="text-2xl font-bold text-indigo-900">Complete Payment</h1>
  <p class="text-sm text-gray-600 mt-1">
      Please complete your payment for order <?= (int) $orderId ?>.
  </p>
  <?php if ($holdExpiresTs > 0): ?>
  <p class="text-sm text-amber-800 mt-2" id="hold-countdown"
     data-expires="<?= (int) $holdExpiresTs ?>">
     Your order will be held for <span id="hold-remaining">…</span>.
  </p>
  <?php endif; ?>
</div>

<?php render_order_summary($order, ['total_label' => 'Total Due']); ?>

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

      // loadActions once — required before confirm(); also forces a read of session.total
      // so Stripe will allow confirm() (docs require displaying/reading the amount).
      var checkoutActions = null;
      if (typeof checkout.loadActions === 'function') {
        var loaded = await checkout.loadActions();
        if (loaded && loaded.type === 'error') {
          throw (loaded.error && loaded.error.message)
            ? new Error(loaded.error.message)
            : new Error('Could not prepare payment.');
        }
        checkoutActions = loaded && loaded.actions ? loaded.actions : null;
        if (checkoutActions && typeof checkoutActions.getSession === 'function') {
          var sessionNow = checkoutActions.getSession();
          if (sessionNow && sessionNow.total && sessionNow.total.total) {
            // Touch amount so Stripe treats the total as displayed.
            void sessionNow.total.total.amount;
            if (sessionNow.total.total.amount && payBtn) {
              totalLabel = sessionNow.total.total.amount;
              payBtn.textContent = 'Pay ' + totalLabel;
            }
          }
          canConfirm = !!(sessionNow && sessionNow.canConfirm);
        }
      }

      if (typeof checkout.on === 'function') {
        checkout.on('change', function (session) {
          canConfirm = !!(session && session.canConfirm);
          if (session && session.total && session.total.total && session.total.total.amount && payBtn && !submitting) {
            totalLabel = session.total.total.amount;
            payBtn.textContent = 'Pay ' + totalLabel;
          }
          setPayEnabled();
        });
      } else if (!checkoutActions) {
        canConfirm = true;
      }

      confirmFn = async function (confirmOpts) {
        if (checkoutActions && typeof checkoutActions.confirm === 'function') {
          return await checkoutActions.confirm(confirmOpts || undefined);
        }
        if (typeof checkout.loadActions === 'function') {
          var again = await checkout.loadActions();
          if (again && again.type === 'error') {
            return { type: 'error', error: again.error || { message: 'Could not prepare payment.' } };
          }
          var actions = again && again.actions ? again.actions : null;
          if (!actions || typeof actions.confirm !== 'function') {
            return { type: 'error', error: { message: 'Payment confirm is unavailable.' } };
          }
          checkoutActions = actions;
          return await actions.confirm(confirmOpts || undefined);
        }
        if (typeof checkout.confirm === 'function') {
          return await checkout.confirm(confirmOpts || undefined);
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
        expressCheckoutElement.on('confirm', async function (event) {
          // Must always resolve the Express Checkout confirm — early return without
          // confirm({expressCheckoutConfirmEvent}) triggers Stripe's invalid-confirm error.
          if (holdExpired) {
            showError('Your seat hold expired. Please cancel and place your order again.');
            if (event && typeof event.paymentFailed === 'function') {
              event.paymentFailed({ reason: 'fail', message: 'Order hold expired.' });
            }
            return;
          }
          if (submitting) {
            if (event && typeof event.paymentFailed === 'function') {
              event.paymentFailed({ reason: 'fail', message: 'Payment already in progress.' });
            }
            return;
          }
          clearError();
          submitting = true;
          setPayEnabled();
          try {
            var result;
            if (typeof event.confirm === 'function') {
              // Older Stripe.js path.
              result = await event.confirm();
            } else {
              result = await confirmFn({ expressCheckoutConfirmEvent: event });
            }
            if (result && result.type === 'error') {
              showError((result.error && result.error.message) || 'Payment was not completed.');
            } else if (result && result.error) {
              showError(result.error.message || 'Payment was not completed.');
            }
          } catch (e) {
            showError((e && e.message) ? e.message : 'Payment failed. Please try again.');
            if (event && typeof event.paymentFailed === 'function') {
              try { event.paymentFailed({ reason: 'fail', message: (e && e.message) || 'Payment failed.' }); } catch (_ignored) {}
            }
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
