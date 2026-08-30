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

layout_head('Order — Deacons Ordination Luncheon');
?>
<h1 class="text-2xl font-bold text-indigo-900 mb-1">Luncheon Box Order</h1>
<p class="text-gray-600 mb-6">Select your lunch boxes and pay online to confirm your order.</p>

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
        <span class="text-sm font-medium text-gray-700">Email</span>
        <input type="email" name="email" required maxlength="200" value="<?= e($old['email'] ?? '') ?>"
               class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
      </label>
      <label class="block">
        <span class="text-sm font-medium text-gray-700">Phone</span>
        <input type="tel" name="phone" required maxlength="50" value="<?= e($old['phone'] ?? '') ?>"
               class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
      </label>
    </div>
  </div>

  <div class="card space-y-4">
    <h2 class="font-semibold text-gray-900">Campus <span class="text-red-600">*</span></h2>

    <div class="grid grid-cols-2 gap-3">
      <?php foreach ($campuses as $c): ?>
        <label class="campus-option relative flex cursor-pointer items-center justify-center rounded-lg border-2 px-3 py-5 text-center font-medium text-lg transition
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
             required maxlength="20" autocomplete="off" value="<?= e($old['lift_group'] ?? '') ?>"
             class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
      <datalist id="lift-group-options"></datalist>
      <span class="text-xs text-gray-500" id="lift-group-hint">Choose a campus above to see its life groups, or type your own (max 20 characters).</span>
    </label>
  </div>

  <div class="card space-y-3">
    <h2 class="font-semibold text-gray-900">Choose lunch boxes</h2>
    <p class="text-sm text-gray-500">Up to <?= (int) $maxQty ?> of each box. Availability updates live.</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <?php foreach ($boxes as $b):
        $code     = $b['code'];
        $checked  = in_array($code, $old['boxes'] ?? [], true);
        $qtyOld   = (int) ($old['qty'][$code] ?? 1);
        $qtyOld   = max(1, min($maxQty, $qtyOld));
        $soldOut  = $b['sold_out'];
        $capLeft  = min($maxQty, max(0, (int) $b['remaining']));
      ?>
        <div class="rounded-lg border-2 border-gray-200 p-3 <?= $soldOut ? 'opacity-60' : '' ?>"
             data-box-row="<?= e($code) ?>">
          <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" name="boxes[]" value="<?= e($code) ?>"
                   class="mt-0.5 h-6 w-6 shrink-0 rounded border-gray-400 text-indigo-600 box-check"
                   <?= $checked && !$soldOut ? 'checked' : '' ?> <?= $soldOut ? 'disabled' : '' ?>>
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
          <div class="mt-2 pl-9 flex items-center gap-2">
            <label class="text-sm text-gray-600" for="qty-<?= e($code) ?>">Qty</label>
            <select name="qty[<?= e($code) ?>]" id="qty-<?= e($code) ?>"
                    class="rounded-md border-gray-300 shadow-sm text-sm qty-select"
                    data-qty="<?= e($code) ?>" <?= $soldOut ? 'disabled' : '' ?>>
              <?php for ($i = 1; $i <= $maxQty; $i++): ?>
                <option value="<?= $i ?>" <?= $i === $qtyOld ? 'selected' : '' ?> <?= $i > $capLeft ? 'disabled' : '' ?>><?= $i ?></option>
              <?php endfor; ?>
            </select>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card flex items-center justify-between">
    <div>
      <span class="text-sm text-gray-500">Order total</span>
      <div class="text-2xl font-bold text-indigo-900" id="order-total">$0.00</div>
    </div>
    <button type="submit" class="btn-primary" id="pay-btn" disabled>Pay with card</button>
  </div>
  <p class="text-xs text-gray-500 text-center">You'll be redirected to Stripe to complete payment. Your order is confirmed only after payment.</p>
</form>

<script>
(function () {
  var PRICES = <?= json_encode(array_column($boxes, 'price_cents', 'code')) ?>;
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
      lgHint.textContent = 'Choose a campus above to see its life groups, or type your own (max 20 characters).';
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

  // Campus is required but its radios are visually hidden, so guard the submit
  // ourselves (native validation can't focus a hidden control).
  form.addEventListener('submit', function (e) {
    if (!campusChosen()) {
      e.preventDefault();
      campusError.classList.remove('hidden');
      campusError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
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

  function recalc() {
    var cents = 0, any = false;
    form.querySelectorAll('.box-check').forEach(function (cb) {
      var code = cb.value;
      var qty = parseInt(form.querySelector('[data-qty="' + code + '"]').value, 10) || 1;
      if (cb.checked && !cb.disabled) { cents += (PRICES[code] || 0) * qty; any = true; }
    });
    totalEl.textContent = '$' + (cents / 100).toFixed(2);
    payBtn.disabled = !any;
  }

  function applyRemaining(data) {
    if (!data || !data.ok) return;
    if (data.open === false) { location.reload(); return; }
    Object.keys(data.boxes).forEach(function (code) {
      var info = data.boxes[code];
      var label = form.querySelector('[data-remaining="' + code + '"]');
      var cb = form.querySelector('.box-check[value="' + code + '"]');
      var sel = form.querySelector('[data-qty="' + code + '"]');
      if (!label || !cb || !sel) return;
      var cap = <?= json_encode(array_column($boxes, 'cap', 'code')) ?>[code];
      if (info.sold_out) {
        label.textContent = 'Sold out';
        label.className = 'block text-xs text-red-600 font-semibold';
        cb.checked = false; cb.disabled = true; sel.disabled = true;
      } else {
        label.textContent = info.remaining + ' of ' + cap + ' left';
        label.className = 'block text-xs text-emerald-700';
        cb.disabled = false; sel.disabled = false;
        var allow = Math.min(MAX, info.remaining);
        Array.prototype.forEach.call(sel.options, function (opt) {
          opt.disabled = parseInt(opt.value, 10) > allow;
        });
        if (parseInt(sel.value, 10) > allow) sel.value = String(allow || 1);
      }
    });
    recalc();
  }

  form.addEventListener('change', recalc);
  recalc();

  // Touching a Qty control implies you want that box — tick its checkbox so a
  // quantity is never submitted without its box selected.
  form.querySelectorAll('.qty-select').forEach(function (sel) {
    var claim = function () {
      var cb = form.querySelector('.box-check[value="' + sel.dataset.qty + '"]');
      if (cb && !cb.disabled && !cb.checked) { cb.checked = true; recalc(); }
    };
    sel.addEventListener('click', claim);
    sel.addEventListener('change', claim);
  });

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
