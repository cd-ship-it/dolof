<?php
/**
 * Public ordering form. Also re-rendered by create-checkout.php on error, which
 * pre-populates $form_errors (string[]) and $old (assoc: field => value,
 * plus $old['qty'][code] and $old['boxes'][] selected codes).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/boxes.php';
require_once __DIR__ . '/includes/layout.php';

auth_start_session();

$form_errors = $form_errors ?? [];
$old         = $old ?? [];
$boxes       = boxes_with_remaining($pdo);
$open        = ordering_is_open($pdo);
$maxQty      = DOLOS_MAX_QTY_PER_BOX;
$cancelled   = isset($_GET['cancelled']);
$campuses    = ['San Leandro', 'Milpitas', 'Pleasanton', 'Tracy'];
$oldCampus   = $old['campus'] ?? '';
if (!in_array($oldCampus, $campuses, true)) {
    $oldCampus = '';
}

// Campus -> life group suggestions (data/life-groups.json). The field stays free
// text; these only pre-fill as the orderer types.
$lifeGroupsByCampus = [];
$lgFile = __DIR__ . '/data/life-groups.json';
if (is_file($lgFile)) {
    $lifeGroupsByCampus = json_decode((string) file_get_contents($lgFile), true) ?: [];
}

layout_head('Order — Deacons Ordination Lunch Ordering Form');
?>
<!-- <h1 class="text-2xl font-bold text-indigo-900 mb-1">Luncheon Box Order</h1> -->
<!-- <p class="text-gray-600 mb-6">Select your lunch boxes and pay online to confirm your order.</p> -->

<?php if ($cancelled): ?>
  <div class="card mb-6 border-amber-300 bg-amber-50 text-amber-800">
    Payment was not completed, so your order was not placed. Your selections are held for a short time — you can try again below.
  </div>
<?php endif; ?>

<?php if (!$open): ?>
  <div class="card text-center">
    <h2 class="text-lg font-semibold text-gray-800">Ordering is closed</h2>
    <p class="text-gray-600 mt-2">Online ordering for this event is not currently open. Please contact the church office.</p>
  </div>
<?php else: ?>

<?php if ($form_errors): ?>
  <div class="card mb-6 border-red-300 bg-red-50">
    <p class="font-semibold text-red-700 mb-1">Please fix the following:</p>
    <ul class="list-disc list-inside text-sm text-red-700">
      <?php foreach ($form_errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" action="<?= e(APP_URL) ?>/create-checkout" class="space-y-6" id="order-form">
  <?= csrf_input() ?>

  <div class="card space-y-3">
    <h2 class="font-semibold text-gray-900">Choose lunch boxes (by Koi Palace 鯉魚門)<span class="text-red-600">*</span></h2>
    <p class="text-sm text-gray-500">Up to <?= (int) $maxQty ?> of each box. Availability updates live.</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <?php foreach ($boxes as $b):
        $code       = $b['code'];
        $soldOut    = $b['sold_out'];
        $capLeft    = min($maxQty, max(0, (int) $b['remaining']));
        $wasChecked = in_array($code, $old['boxes'] ?? [], true) && !$soldOut;
        $qtyOld     = $wasChecked ? max(1, min($capLeft, (int) ($old['qty'][$code] ?? 1))) : 0;
      ?>
        <div class="rounded-lg border-2 border-gray-200 p-3 <?= $soldOut ? 'opacity-60' : '' ?>"
             data-box-row="<?= e($code) ?>">
          <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" name="boxes[]" value="<?= e($code) ?>"
                   class="mt-0.5 h-6 w-6 shrink-0 rounded border-gray-400 text-indigo-600 box-check"
                   <?= $wasChecked ? 'checked' : '' ?> <?= $soldOut ? 'disabled' : '' ?>>
            <span class="min-w-0">
              <span class="font-semibold text-gray-900">
                <span class="inline-flex items-center justify-center h-6 w-6 rounded bg-indigo-100 text-indigo-800 font-bold"><?= e($code) ?></span>
                <?= e($b['name']) ?>
              </span>
              <span class="block text-gray-500 text-sm"><?= e(money((int) $b['price_cents'])) ?></span>
              <span class="block text-xs <?= $soldOut ? 'text-red-600 font-semibold' : 'text-emerald-700' ?>" data-remaining="<?= e($code) ?>">
                <?= $soldOut ? 'Sold out' : ((int) $b['remaining'] . ' of ' . (int) $b['cap'] . ' left') ?>
              </span>
            </span>
          </label>
          <div class="mt-2 pl-9 flex items-center gap-2" data-stepper="<?= e($code) ?>" data-allow="<?= (int) $capLeft ?>">
            <span class="text-sm text-gray-600 mr-1">Qty</span>
            <button type="button" data-qty-btn="dec" aria-label="Decrease <?= e($code) ?>"
                    class="h-8 w-8 shrink-0 rounded-md border-2 border-gray-300 text-xl leading-none font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">&minus;</button>
            <span data-qty-num class="w-7 text-center text-base font-semibold tabular-nums"><?= (int) $qtyOld ?></span>
            <button type="button" data-qty-btn="inc" aria-label="Increase <?= e($code) ?>"
                    class="h-8 w-8 shrink-0 rounded-md border-2 border-gray-300 text-xl leading-none font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">+</button>
            <input type="hidden" name="qty[<?= e($code) ?>]" value="<?= (int) $qtyOld ?>" data-qty="<?= e($code) ?>">
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card space-y-4">
    <h2 class="font-semibold text-gray-900">Your details</h2>
    <div class="grid sm:grid-cols-2 gap-4">
      <label class="block">
        <span class="text-sm font-medium text-gray-700">First name <span class="text-red-600">*</span></span>
        <input type="text" name="first_name" required maxlength="100" value="<?= e($old['first_name'] ?? '') ?>"
               class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
      </label>
      <label class="block">
        <span class="text-sm font-medium text-gray-700">Last name <span class="text-red-600">*</span></span>
        <input type="text" name="last_name" required maxlength="100" value="<?= e($old['last_name'] ?? '') ?>"
               class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
      </label>
      <label class="block">
        <span class="text-sm font-medium text-gray-700">Email <span class="text-red-600">*</span></span>
        <input type="email" name="email" required maxlength="200" value="<?= e($old['email'] ?? '') ?>"
               class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
      </label>
      <label class="block">
        <span class="text-sm font-medium text-gray-700">Phone</span>
        <input type="tel" name="phone" maxlength="50" value="<?= e($old['phone'] ?? '') ?>"
               class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
      </label>
    </div>
  </div>

  <div class="card space-y-4">
    <h2 class="font-semibold text-gray-900">Campus <span class="text-red-600">*</span></h2>

    <div class="grid grid-cols-2 gap-3">
      <?php foreach ($campuses as $c): ?>
        <label class="campus-option relative flex cursor-pointer items-center justify-center rounded-lg border-2 px-2 py-2 text-center font-medium text-sm transition
                      <?= $oldCampus === $c ? 'border-indigo-600 bg-indigo-50 text-indigo-800 ring-2 ring-indigo-300' : 'border-amber-400 bg-amber-50 text-gray-800 hover:border-indigo-400' ?>">
          <input type="radio" name="campus" value="<?= e($c) ?>" class="sr-only campus-radio" <?= $oldCampus === $c ? 'checked' : '' ?>>
          <?= e($c) ?>
        </label>
      <?php endforeach; ?>
    </div>
    <p id="campus-error" class="hidden text-sm font-medium text-red-600">Please choose a campus to continue.</p>

    <label class="block">
      <span class="font-semibold text-gray-900">Lift Group Name</span>
      <input type="text" name="lift_group" id="lift-group-input" list="lift-group-options"
             maxlength="20" autocomplete="off" value="<?= e($old['lift_group'] ?? '') ?>"
             class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
      <datalist id="lift-group-options"></datalist>
      <span class="text-xs text-gray-500" id="lift-group-hint">Choose your campus above to see its life groups, or type your own (max 20 characters).</span>
    </label>
  </div>

  <div class="card flex items-center justify-between">
    <div>
      <span class="text-sm text-gray-500">Order total</span>
      <div class="text-2xl font-bold text-indigo-900" id="order-total">$0.00</div>
    </div>
    <button type="submit" class="btn-primary" id="pay-btn" disabled>Continue</button>
  </div>
  <!-- <p class="text-xs text-gray-500 text-center">You'll be redirected to Stripe to complete payment. Your order is confirmed only after payment.</p> -->
</form>

<div id="confirm-modal" class="hidden fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-3">
  <div class="my-4 w-full max-w-md rounded-xl bg-white p-4 shadow-xl space-y-3 text-sm">
    <h2 class="text-base font-bold text-indigo-900">Please review your order</h2>

    <div class="text-gray-800 space-y-0.5 text-xs">
      <div><span class="text-gray-500">Name:</span> <span id="sum-name"></span></div>
      <div><span class="text-gray-500">Email:</span> <span id="sum-email"></span></div>
      <div><span class="text-gray-500">Campus:</span> <span id="sum-campus"></span></div>
      <div id="sum-lg-row"><span class="text-gray-500">Lift Group:</span> <span id="sum-lg"></span></div>
      <div id="sum-phone-row"><span class="text-gray-500">Phone:</span> <span id="sum-phone"></span></div>
    </div>

    <table class="w-full text-xs border-t border-gray-200 pt-1">
      <tbody id="sum-rows"></tbody>
      <tfoot>
        <tr class="border-t border-gray-200">
          <td class="pt-2 font-bold text-sm">Total to pay</td>
          <td></td>
          <td class="pt-2 text-right text-base font-bold text-indigo-900" id="sum-total">$0.00</td>
        </tr>
      </tfoot>
    </table>

    <button type="button" id="confirm-go" class="btn-primary w-full text-center">Confirm &amp; Pay with card</button>

    <p class="text-xs text-gray-500">Next you'll be taken to Stripe to pay this amount by card. Your order is confirmed only after payment succeeds.</p>

    <button type="button" id="confirm-back"
            class="w-full rounded-md border-2 border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
      ← Back to edit
    </button>
  </div>
</div>

<script>
(function () {
  var PRICES = <?= json_encode(array_column($boxes, 'price_cents', 'code')) ?>;
  var BOX_NAMES = <?= json_encode(array_column($boxes, 'name', 'code'), JSON_UNESCAPED_SLASHES) ?>;
  var MAX = <?= (int) $maxQty ?>;
  var form = document.getElementById('order-form');
  var totalEl = document.getElementById('order-total');
  var payBtn = document.getElementById('pay-btn');

  // Campus radio: highlight the chosen card.
  var campusError = document.getElementById('campus-error');
  var SEL = 'border-indigo-600 bg-indigo-50 text-indigo-800 ring-2 ring-indigo-300'.split(' ');
  var UNSEL = 'border-amber-400 bg-amber-50 text-gray-800 hover:border-indigo-400'.split(' ');
  function campusChosen() { return form.querySelector('.campus-radio:checked'); }
  function syncCampus() {
    form.querySelectorAll('.campus-option').forEach(function (opt) {
      var on = opt.querySelector('.campus-radio').checked;
      SEL.forEach(function (c) { opt.classList.toggle(c, on); });
      UNSEL.forEach(function (c) { opt.classList.toggle(c, !on); });
    });
    if (campusChosen()) { campusError.classList.add('hidden'); }
  }
  // Campus -> life group suggestions. Field stays free text: unknown values are kept.
  var LIFE_GROUPS = <?= json_encode($lifeGroupsByCampus, JSON_UNESCAPED_SLASHES) ?>;
  var lgInput = document.getElementById('lift-group-input');
  var lgList = document.getElementById('lift-group-options');
  var lgHint = document.getElementById('lift-group-hint');

  function refreshLifeGroups(userChanged) {
    var chosen = form.querySelector('.campus-radio:checked');
    var groups = (chosen && LIFE_GROUPS[chosen.value]) ? LIFE_GROUPS[chosen.value] : [];
    lgList.innerHTML = '';
    groups.forEach(function (name) {
      var o = document.createElement('option');
      o.value = name;
      lgList.appendChild(o);
    });
    if (!chosen) {
      lgHint.textContent = 'Choose your campus above to see its life groups, or type your own (max 20 characters).';
    } else if (groups.length) {
      lgHint.textContent = 'Start typing to pick a ' + chosen.value + ' life group, or enter your own (max 20 characters).';
    } else {
      lgHint.textContent = 'Enter your life group name (max 20 characters).';
    }
    // Only wipe the field on an actual campus switch, and only if it held a
    // suggestion from the previous campus (keep anything the user typed themselves).
    if (userChanged && lgInput.value && ALL_GROUPS.indexOf(lgInput.value) !== -1
        && groups.indexOf(lgInput.value) === -1) {
      lgInput.value = '';
    }
  }
  var ALL_GROUPS = Object.keys(LIFE_GROUPS).reduce(function (acc, k) {
    return acc.concat(LIFE_GROUPS[k]);
  }, []);

  form.querySelectorAll('.campus-radio').forEach(function (r) {
    r.addEventListener('change', function () { syncCampus(); refreshLifeGroups(true); });
  });
  syncCampus();
  refreshLifeGroups(false);

  // Submit flow: campus guard, then an order-review step before Stripe.
  var confirmed = false;
  var modal = document.getElementById('confirm-modal');
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

  function buildSummary() {
    var f = function (n) { var el = form.querySelector('[name="' + n + '"]'); return el ? el.value.trim() : ''; };
    var campus = campusChosen() ? campusChosen().value : '';
    var lg = f('lift_group'), phone = f('phone');

    document.getElementById('sum-name').textContent = (f('first_name') + ' ' + f('last_name')).trim();
    document.getElementById('sum-email').textContent = f('email');
    document.getElementById('sum-campus').textContent = campus;
    document.getElementById('sum-lg').textContent = lg;
    document.getElementById('sum-lg-row').style.display = lg ? '' : 'none';
    document.getElementById('sum-phone').textContent = phone;
    document.getElementById('sum-phone-row').style.display = phone ? '' : 'none';

    var rows = '', total = 0;
    form.querySelectorAll('input[data-qty]').forEach(function (h) {
      var code = h.dataset.qty;
      var qty = parseInt(h.value, 10) || 0;
      var cb = form.querySelector('.box-check[value="' + code + '"]');
      if (qty <= 0 || (cb && cb.disabled)) return;
      var sub = (PRICES[code] || 0) * qty;
      total += sub;
      rows += '<tr>'
        + '<td class="py-1 pr-3"><span class="font-semibold text-indigo-700">' + esc(code) + '</span> ' + esc(BOX_NAMES[code] || '') + '</td>'
        + '<td class="py-1 px-2 text-center whitespace-nowrap">' + qty + ' &times; $' + ((PRICES[code] || 0) / 100).toFixed(2) + '</td>'
        + '<td class="py-1 pl-3 text-right font-medium">$' + (sub / 100).toFixed(2) + '</td>'
        + '</tr>';
    });
    document.getElementById('sum-rows').innerHTML = rows;
    document.getElementById('sum-total').textContent = '$' + (total / 100).toFixed(2);
  }

  function openModal() { buildSummary(); modal.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
  function closeModal() { modal.classList.add('hidden'); document.body.style.overflow = ''; }

  form.addEventListener('submit', function (e) {
    if (!campusChosen()) {
      e.preventDefault();
      campusError.classList.remove('hidden');
      campusError.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
    if (!confirmed) {
      e.preventDefault();
      openModal();
    }
  });

  document.getElementById('confirm-back').addEventListener('click', closeModal);
  modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal(); });
  document.getElementById('confirm-go').addEventListener('click', function () {
    confirmed = true;
    closeModal();
    if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
  });

  // Live US phone formatting: (123) 456-7890
  var phoneEl = form.querySelector('input[name="phone"]');
  function formatPhone(v) {
    var d = (v || '').replace(/\D/g, '');
    if (d.length === 11 && d[0] === '1') { d = d.slice(1); }
    d = d.slice(0, 10);
    if (d.length === 0) return '';
    if (d.length < 4) return '(' + d;
    if (d.length < 7) return '(' + d.slice(0, 3) + ') ' + d.slice(3);
    return '(' + d.slice(0, 3) + ') ' + d.slice(3, 6) + '-' + d.slice(6);
  }
  if (phoneEl) {
    var reformat = function () { phoneEl.value = formatPhone(phoneEl.value); };
    phoneEl.addEventListener('input', reformat);
    phoneEl.addEventListener('blur', reformat);
    reformat();
  }

  var BOX_ON = 'border-indigo-600 bg-indigo-50 ring-2 ring-indigo-300'.split(' ');
  function syncBoxHighlight() {
    form.querySelectorAll('[data-box-row]').forEach(function (row) {
      var cb = row.querySelector('.box-check');
      var on = cb && cb.checked && !cb.disabled;
      BOX_ON.forEach(function (c) { row.classList.toggle(c, on); });
      row.classList.toggle('border-gray-200', !on);
    });
  }

  function qtyOf(code) {
    var h = form.querySelector('input[data-qty="' + code + '"]');
    return h ? (parseInt(h.value, 10) || 0) : 0;
  }

  function recalc() {
    var cents = 0, any = false;
    form.querySelectorAll('input[data-qty]').forEach(function (h) {
      var code = h.dataset.qty;
      var qty = parseInt(h.value, 10) || 0;
      var cb = form.querySelector('.box-check[value="' + code + '"]');
      if (qty > 0 && cb && !cb.disabled) { cents += (PRICES[code] || 0) * qty; any = true; }
    });
    totalEl.textContent = '$' + (cents / 100).toFixed(2);
    payBtn.disabled = !any;
    syncBoxHighlight();
  }

  function applyRemaining(data) {
    if (!data || !data.ok) return;
    if (data.open === false) { location.reload(); return; }
    Object.keys(data.boxes).forEach(function (code) {
      var info = data.boxes[code];
      var label = form.querySelector('[data-remaining="' + code + '"]');
      var cb = form.querySelector('.box-check[value="' + code + '"]');
      var wrap = form.querySelector('[data-stepper="' + code + '"]');
      if (!label || !cb || !wrap) return;
      var cap = <?= json_encode(array_column($boxes, 'cap', 'code')) ?>[code];
      if (info.sold_out) {
        label.textContent = 'Sold out';
        label.className = 'block text-xs text-red-600 font-semibold';
        cb.disabled = true;
        wrap.dataset.allow = '0';
        if (wrap._setQty) wrap._setQty(0);
      } else {
        label.textContent = info.remaining + ' of ' + cap + ' left';
        label.className = 'block text-xs text-emerald-700';
        cb.disabled = false;
        wrap.dataset.allow = String(Math.min(MAX, info.remaining));
        if (wrap._setQty) wrap._setQty(qtyOf(code)); // re-clamp to the new max
      }
    });
    recalc();
  }

  // Qty steppers — default 0. +/- adjust; the box checkbox toggles 0 <-> 1.
  form.querySelectorAll('[data-stepper]').forEach(function (wrap) {
    var code   = wrap.dataset.stepper;
    var hidden = wrap.querySelector('input[data-qty]');
    var numEl  = wrap.querySelector('[data-qty-num]');
    var dec    = wrap.querySelector('[data-qty-btn="dec"]');
    var inc    = wrap.querySelector('[data-qty-btn="inc"]');
    var cb     = form.querySelector('.box-check[value="' + code + '"]');

    function maxAllowed() {
      return Math.max(0, Math.min(MAX, parseInt(wrap.dataset.allow || MAX, 10)));
    }
    function setQty(n) {
      n = Math.max(0, Math.min(maxAllowed(), n | 0));
      hidden.value = String(n);
      numEl.textContent = String(n);
      if (cb) cb.checked = n > 0;
      var locked = cb && cb.disabled;
      dec.disabled = locked || n <= 0;
      inc.disabled = locked || n >= maxAllowed();
      recalc();
    }
    wrap._setQty = setQty;

    dec.addEventListener('click', function () { setQty((parseInt(hidden.value, 10) || 0) - 1); });
    inc.addEventListener('click', function () { setQty((parseInt(hidden.value, 10) || 0) + 1); });
    if (cb) {
      cb.addEventListener('change', function () {
        var cur = parseInt(hidden.value, 10) || 0;
        setQty(cb.checked ? (cur > 0 ? cur : 1) : 0);
      });
    }

    setQty(parseInt(hidden.value, 10) || 0);
  });

  form.addEventListener('change', recalc);
  recalc();

  function poll() {
    fetch('<?= e(APP_URL) ?>/remaining-counts', { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(applyRemaining)
      .catch(function () {});
  }
  poll();
  setInterval(poll, 15000);
})();
</script>

<?php endif; ?>
<?php layout_footer(); ?>
