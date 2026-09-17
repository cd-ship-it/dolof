<?php
/**
 * Stripe redirect target. Confirms the payment (idempotent with the webhook)
 * and shows the order summary. Also handles free RSVP confirmations.
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
require_once __DIR__ . '/vendor/autoload.php';

auth_start_session();

$order  = null;
$isRsvp = isset($_GET['rsvp']) && $_GET['rsvp'] === '1';

if ($isRsvp) {
    $orderId = (int) ($_SESSION['rsvp_order_id'] ?? 0);
    unset($_SESSION['rsvp_order_id']);
    if ($orderId > 0) {
        $candidate = order_get_with_items($pdo, $orderId);
        if ($candidate && ($candidate['payment_method'] ?? '') === 'rsvp' && $candidate['status'] === 'paid') {
            $order = $candidate;
        }
    }
    if ($order === null) {
        header('Location: ' . APP_URL . '/order', true, 302);
        exit;
    }
} else {
    $sessionId = $_GET['session_id'] ?? '';
    if ($sessionId === '') {
        header('Location: ' . APP_URL . '/order', true, 302);
        exit;
    }

    try {
        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
        $session = \Stripe\Checkout\Session::retrieve($sessionId);

        if ($session
            && $session->payment_status === 'paid'
            && ($session->metadata->source ?? '') === 'dolos'
            && !empty($session->metadata->order_id)
        ) {
            $orderId = (int) $session->metadata->order_id;
            $amount  = isset($session->amount_total) ? (int) $session->amount_total : null;
            payment_finalize_and_notify($pdo, $orderId, $sessionId, $amount);
            $order = order_get_with_items($pdo, $orderId);
            if (isset($_SESSION['stripe_pay'])) {
                unset($_SESSION['stripe_pay']);
            }
        } else {
            app_log('high', 'Payment', 'success page: not paid / bad metadata', [
                'stripe_session_id' => $sessionId,
                'payment_status'    => $session->payment_status ?? null,
            ]);
        }
    } catch (Throwable $e) {
        app_log('high', 'Payment', 'success page error', ['error' => $e->getMessage()]);
    }
}

layout_head('Thank you — Deacons Ordination Lunch Ordering Form');
?>
<?php if ($order && $order['status'] === 'paid'): ?>
  <?php
    $confirmEmail = trim((string) ($order['email'] ?? ''));
    $confirmEmailOk = $confirmEmail !== '' && filter_var($confirmEmail, FILTER_VALIDATE_EMAIL);
    $adultLines = array_map('format_attendee_line', decode_attendees($order['adult_names'] ?? null));
    $childLines = array_map('format_attendee_line', decode_attendees($order['child_names'] ?? null));
    $isRsvpOrder = ($order['payment_method'] ?? '') === 'rsvp';
  ?>
  <div class="card text-center mb-6">
    <div class="text-4xl mb-2">🎉</div>
    <h1 class="text-2xl font-bold text-indigo-900"><?= $isRsvpOrder ? 'RSVP confirmed!' : '午餐訂購完成!' ?></h1>
    <?php if ($confirmEmailOk): ?>
      <p class="text-gray-600 mt-2">A confirmation email is on its way to <strong><?= e($confirmEmail) ?></strong>.</p>
    <?php else: ?>
      <p class="text-gray-600 mt-2">No email was provided — a confirmation was sent to the church office<?= trim((string) ($order['phone'] ?? '')) !== '' ? ' (phone on file: <strong>' . e($order['phone']) . '</strong>)' : '' ?>.</p>
    <?php endif; ?>
  </div>
  <div class="card">
    <h2 class="font-semibold text-gray-900 mb-3"><?= $isRsvpOrder ? 'RSVP' : 'Order' ?> #<?= (int) $order['id'] ?></h2>
    <p class="text-sm text-gray-600 mb-4">
      <?= e(trim($order['first_name'] . ' ' . $order['last_name'])) ?> &middot; <?= e($order['phone']) ?><br>
      <span class="text-gray-500">Campus:</span> <?= e($order['campus']) ?>
      &middot; <span class="text-gray-500">Lift Group:</span> <?= e($order['lift_group']) ?>
    </p>
    <?php if ($adultLines || $childLines): ?>
    <div class="text-sm text-gray-700 mb-4 space-y-1">
      <?php if ($adultLines): ?>
        <div><span class="text-gray-500">Adults:</span> <?= e(implode('; ', $adultLines)) ?></div>
      <?php endif; ?>
      <?php if ($childLines): ?>
        <div><span class="text-gray-500">Children:</span> <?= e(implode('; ', $childLines)) ?></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($order['items']): ?>
    <table class="w-full text-sm">
      <thead><tr class="text-left text-gray-500 border-b">
        <th class="py-1">Box</th><th class="py-1 text-center">Qty</th><th class="py-1 text-right">Subtotal</th>
      </tr></thead>
      <tbody>
      <?php foreach ($order['items'] as $it): ?>
        <tr class="border-b border-gray-100">
          <td class="py-2"><?= dish_name_html($it['box_name']) ?></td>
          <td class="py-2 text-center"><?= (int) $it['quantity'] ?></td>
          <td class="py-2 text-right"><?= e(money((int) $it['unit_price_cents'] * (int) $it['quantity'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr>
        <td class="pt-3 font-semibold" colspan="2">Total paid</td>
        <td class="pt-3 font-semibold text-right"><?= e(money((int) $order['total_amount_cents'])) ?></td>
      </tr></tfoot>
    </table>
    <?php else: ?>
    <p class="text-sm text-gray-600">No lunch boxes ordered — attendance only.</p>
    <?php endif; ?>
  </div>
  <div class="mt-6 flex flex-col sm:flex-row items-stretch sm:items-center justify-center gap-3">
    <button type="button" id="close-window" class="btn-primary w-full sm:w-auto">Close this window</button>
    <a href="<?= e(APP_URL) ?>/order" class="btn-secondary inline-block text-center w-full sm:w-auto">Submit another order</a>
  </div>
  <script>
  (function () {
    var btn = document.getElementById('close-window');
    if (!btn) return;
    btn.addEventListener('click', function () {
      window.close();
      // Browsers block close() unless this tab was opened by script.
      setTimeout(function () {
        if (!window.closed) {
          btn.textContent = 'You can close this tab now';
          btn.disabled = true;
        }
      }, 150);
    });
  })();
  </script>
<?php else: ?>
  <div class="card text-center">
    <h1 class="text-xl font-bold text-gray-800">Payment received</h1>
    <p class="text-gray-600 mt-2">If you just paid, your order is being finalized. You'll get a confirmation email shortly — you may close this page.</p>
    <div class="mt-4 flex flex-col sm:flex-row items-stretch sm:items-center justify-center gap-3">
      <button type="button" id="close-window-pending" class="btn-primary w-full sm:w-auto">Close this window</button>
      <a href="<?= e(APP_URL) ?>/order" class="btn-secondary inline-block text-center w-full sm:w-auto">Submit another order</a>
    </div>
  </div>
  <script>
  (function () {
    var btn = document.getElementById('close-window-pending');
    if (!btn) return;
    btn.addEventListener('click', function () {
      window.close();
      setTimeout(function () {
        if (!window.closed) {
          btn.textContent = 'You can close this tab now';
          btn.disabled = true;
        }
      }, 150);
    });
  })();
  </script>
<?php endif; ?>
<?php layout_footer(); ?>
