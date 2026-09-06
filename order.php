<?php
/**
 * Public ordering form. Also re-rendered by create-checkout.php on error, which
 * pre-populates $form_errors (string[]) and $old (assoc: field => value,
 * plus $old['qty'][code], $old['boxes'][] selected codes,
 * $old['attending_adults'], $old['attending_children'],
 * $old['adult_names'], $old['child_names']).
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

// Campuses + life-group suggestions: single source = data/life-groups.json
$lifeGroupsByCampus = life_groups_by_campus();
$campuses           = campuses();
$campusesConfigured = $campuses !== [];
$oldCampus          = $old['campus'] ?? '';
if (!in_array($oldCampus, $campuses, true)) {
    $oldCampus = '';
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

  <div class="card space-y-4 transition-opacity duration-200" data-form-step="details">
    <h2 class="font-semibold text-gray-900">Your details</h2>
    <div class="grid sm:grid-cols-2 gap-4">
      <label class="block">
        <span class="text-md font-medium text-gray-700">First name <span class="text-red-600">*</span></span>
        <input type="text" name="first_name" required maxlength="100" value="<?= e($old['first_name'] ?? '') ?>"
               class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
      </label>
      <label class="block">
        <span class="text-md font-medium text-gray-700">Last name <span class="text-red-600">*</span></span>
        <input type="text" name="last_name" required maxlength="100" value="<?= e($old['last_name'] ?? '') ?>"
               class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
      </label>
    </div>
    <hr class="border-gray-200">
    <p class="text-sm font-medium text-gray-700"><span class="text-red-600">*</span> Email or phone is required (at least one). Email is recommanded so you can receive a electronic receipt.</p>
    <div class="grid sm:grid-cols-2 gap-4">
      <label class="block">
        <span class="text-md font-medium text-gray-700">Email</span>
        <input type="email" name="email" maxlength="200" value="<?= e($old['email'] ?? '') ?>"
               class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
      </label>
      <label class="block">
        <span class="text-md font-medium text-gray-700">Phone</span>
        <input type="tel" name="phone" maxlength="50" value="<?= e($old['phone'] ?? '') ?>"
               class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
      </label>
    </div>
    <p id="contact-error" class="hidden text-sm font-medium text-red-600">Please enter a valid email or a phone number.</p>
  </div>
  <?php
    $attAdultsOld   = max(0, min(50, (int) ($old['attending_adults'] ?? 0)));
    $attChildrenOld = max(0, min(50, (int) ($old['attending_children'] ?? 0)));
    $adultNamesOld  = array_values(array_map('strval', (array) ($old['adult_names'] ?? [])));
    $childNamesOld  = array_values(array_map('strval', (array) ($old['child_names'] ?? [])));
  ?>
  <div class="card space-y-4 transition-opacity duration-200 form-step-locked" data-form-step="attendance" aria-disabled="true">
    <h2 class="font-semibold text-gray-900">Attendance <span class="text-red-600">*</span></h2>
    <div class="space-y-3">
      <div class="space-y-2">
        <div class="flex items-center justify-between gap-3">
          <span class="text-sm font-medium text-gray-700">Adult</span>
          <div class="flex items-center gap-2" data-attend-stepper="attending_adults" data-attend-min="0" data-attend-max="50">
            <button type="button" data-attend-btn="dec" aria-label="Decrease adults"
                    class="h-8 w-8 shrink-0 rounded-md border-2 border-gray-300 text-xl leading-none font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">&minus;</button>
            <span data-attend-num class="w-7 text-center text-base font-semibold tabular-nums"><?= (int) $attAdultsOld ?></span>
            <button type="button" data-attend-btn="inc" aria-label="Increase adults"
                    class="h-8 w-8 shrink-0 rounded-md border-2 border-gray-300 text-xl leading-none font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">+</button>
            <input type="hidden" name="attending_adults" value="<?= (int) $attAdultsOld ?>" data-attend="attending_adults">
          </div>
        </div>
        <div id="adult-names" class="space-y-2 <?= $attAdultsOld >= 1 ? '' : 'hidden' ?>"
             data-name-list="adult" data-name-label="Adult" data-name-min="1"
             data-initial-names="<?= e(json_encode($adultNamesOld, JSON_UNESCAPED_UNICODE)) ?>"></div>
      </div>
      <div class="space-y-2">
        <div class="flex items-center justify-between gap-3">
          <span class="text-sm font-medium text-gray-700">Children <span class="text-gray-500 font-normal">(Age 12 and below)</span></span>
          <div class="flex items-center gap-2" data-attend-stepper="attending_children" data-attend-min="0" data-attend-max="50">
            <button type="button" data-attend-btn="dec" aria-label="Decrease children"
                    class="h-8 w-8 shrink-0 rounded-md border-2 border-gray-300 text-xl leading-none font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">&minus;</button>
            <span data-attend-num class="w-7 text-center text-base font-semibold tabular-nums"><?= (int) $attChildrenOld ?></span>
            <button type="button" data-attend-btn="inc" aria-label="Increase children"
                    class="h-8 w-8 shrink-0 rounded-md border-2 border-gray-300 text-xl leading-none font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">+</button>
            <input type="hidden" name="attending_children" value="<?= (int) $attChildrenOld ?>" data-attend="attending_children">
          </div>
        </div>
        <div id="child-names" class="space-y-2 <?= $attChildrenOld >= 1 ? '' : 'hidden' ?>"
             data-name-list="child" data-name-label="Child" data-name-min="1"
             data-initial-names="<?= e(json_encode($childNamesOld, JSON_UNESCAPED_UNICODE)) ?>"></div>
      </div>
    </div>
  </div>
  <div class="card space-y-4 transition-opacity duration-200 form-step-locked" data-form-step="campus" aria-disabled="true">
    <h2 class="font-semibold text-gray-900">Campus <span class="text-red-600">*</span></h2>

    <?php if (!$campusesConfigured): ?>
      <div class="rounded-lg border border-red-300 bg-red-50 px-3 py-3 text-sm text-red-700">
        <p class="font-semibold">Campus options are not configured.</p>
        <p class="mt-1">Please contact the church office — online ordering cannot continue until campuses are defined.</p>
      </div>
    <?php else: ?>
    <div class="grid grid-cols-2 gap-3">
      <?php foreach ($campuses as $c): $wide = strlen($c) > 16; ?>
        <label class="campus-option relative flex cursor-pointer items-center justify-center rounded-lg border-2 px-2 py-2 text-center font-medium text-sm transition <?= $wide ? 'col-span-2' : '' ?>
                      <?= $oldCampus === $c ? 'border-indigo-600 bg-indigo-50 text-indigo-800 ring-2 ring-indigo-300' : 'border-amber-400 bg-amber-50 text-gray-800 hover:border-indigo-400' ?>">
          <input type="radio" name="campus" value="<?= e($c) ?>" class="sr-only campus-radio" <?= $oldCampus === $c ? 'checked' : '' ?>>
          <?= e($c) ?>
        </label>
      <?php endforeach; ?>
    </div>
    <p id="campus-error" class="hidden text-sm font-medium text-red-600">Please choose a campus to continue.</p>
    <?php endif; ?>

    <div class="block space-y-2">
      <label for="lift-group-input" class="font-semibold text-gray-900">Lift Group Name <span class="text-red-600">*</span></label>
      <div class="relative mt-1">
        <input type="text" name="lift_group" id="lift-group-input"
               required maxlength="20" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false"
               role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="lift-group-list"
               value="<?= e($old['lift_group'] ?? '') ?>"
               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
               <?= $campusesConfigured ? '' : 'disabled' ?>>
        <ul id="lift-group-list" role="listbox"
            class="hidden absolute z-20 left-0 right-0 mt-1 max-h-56 overflow-auto rounded-md border border-gray-200 bg-white text-sm shadow-lg"></ul>
      </div>
      <?php
        $noLgLabel = "I don't join a Life Group";
        $noLgValue = 'No Life Group';
        $noLgSelected = ($old['lift_group'] ?? '') === $noLgValue;
      ?>
      <button type="button" id="no-life-group-option"
              class="w-full rounded-lg border-2 px-2 py-2 text-center font-medium text-sm transition
                     <?= $noLgSelected ? 'border-indigo-600 bg-indigo-50 text-indigo-800 ring-2 ring-indigo-300' : 'border-amber-400 bg-amber-50 text-gray-800 hover:border-indigo-400' ?>"
              data-lg-value="<?= e($noLgValue) ?>">
        <?= e($noLgLabel) ?>
      </button>
      <span class="text-xs text-gray-500 block" id="lift-group-hint">Choose your campus above to see its life groups, or type your own (max 20 characters). Required.</span>
      <p id="lift-group-error" class="hidden text-sm font-medium text-red-600">Please enter a Lift Group Name, or choose “I don't join a Life Group”.</p>
    </div>
  </div>



  <div class="card space-y-3 transition-opacity duration-200 form-step-locked" data-form-step="boxes" aria-disabled="true">

    <h2 class="font-semibold text-gray-900">Choose lunch boxes<span class="text-red-600">*</span></h2>
    <!-- <p class="text-sm text-gray-500">Up to <?= (int) $maxQty ?> of each box. Availability updates live.</p> -->
    <p class="text-sm text-gray-500">$15.00 per box (Tax incl.) Max one box per person.</p>
    <p class="text-sm text-gray-500"></p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <?php foreach ($boxes as $b):
        $code       = $b['code'];
        $soldOut    = $b['sold_out'];
        $capLeft    = min($maxQty, max(0, (int) $b['remaining']));
        $wasChecked = in_array($code, $old['boxes'] ?? [], true) && !$soldOut;
        $qtyOld     = $wasChecked ? max(1, min($capLeft, (int) ($old['qty'][$code] ?? 1))) : 0;
      ?>
        <div class="rounded-lg border-2 border-gray-200 p-3 <?= $soldOut ? 'opacity-60' : 'cursor-pointer select-none' ?>"
             data-box-row="<?= e($code) ?>">
          <label class="flex items-start gap-3 <?= $soldOut ? '' : 'cursor-pointer' ?>">
            <input type="checkbox" name="boxes[]" value="<?= e($code) ?>"
                   class="mt-0.5 h-6 w-6 shrink-0 rounded border-gray-400 text-indigo-600 box-check"
                   <?= $wasChecked ? 'checked' : '' ?> <?= $soldOut ? 'disabled' : '' ?>>
            <span class="min-w-0">
              <span class="font-semibold text-gray-900">
                <span class="inline-flex items-center justify-center h-6 w-6 rounded bg-indigo-100 text-indigo-800 font-bold"><?= e($code) ?></span>
                <?= dish_name_html($b['name']) ?>
              </span>
              <!-- <span class="block text-gray-500 text-sm"><?= e(money((int) $b['price_cents'])) ?></span> -->
              <?php
                $rem = (int) $b['remaining'];
                $remText = $soldOut ? 'Sold out' : ($rem <= DOLOS_LOW_STOCK_THRESHOLD ? $rem . ' left' : '');
              ?>
              <span class="block text-xs min-h-[1em] <?= $soldOut ? 'text-red-600 font-semibold' : 'text-amber-600 font-medium' ?>" data-remaining="<?= e($code) ?>"><?= e($remText) ?></span>
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
    <div class="flex flex-col items-center text-center gap-1">
      <img src="<?= e(APP_URL) ?>/img/koi-palace.webp" alt="Koi Palace 鯉魚門" class="h-16 w-auto">
      <p class="text-xs font-medium text-gray-400">Proudly brought to you by Koi Palace.</p>
    </div>
  </div>

  <p id="boxes-over-attend" class="hidden text-sm font-medium text-red-600 text-center -mt-2" role="alert">
    Total lunch boxes cannot exceed total attendance (max one box per person).
  </p>
  <p id="boxes-attend-summary" class="hidden text-sm text-gray-600 text-center -mt-2">
    You have ordered <span id="boxes-attend-boxes">0</span> lunch boxes for <span id="boxes-attend-people">0</span> people.
  </p>

  <div class="card flex items-center justify-between transition-opacity duration-200 form-step-locked" data-form-step="checkout" aria-disabled="true">
    <div>
      <span class="text-sm text-gray-500">Order total</span>
      <div class="text-2xl font-bold text-indigo-900" id="order-total">$0.00</div>
    </div>
    <button type="submit" class="btn-primary" id="pay-btn" disabled<?= $campusesConfigured ? '' : ' title="Campus options are not configured"' ?>>Continue</button>
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
      <div><span class="text-gray-500">Adult:</span> <span id="sum-att-adults"></span></div>
      <div id="sum-adult-names-row" class="hidden pl-3 text-gray-600"><span id="sum-adult-names"></span></div>
      <div><span class="text-gray-500">Children (12 &amp; under):</span> <span id="sum-att-children"></span></div>
      <div id="sum-child-names-row" class="hidden pl-3 text-gray-600"><span id="sum-child-names"></span></div>
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
  var LOW_STOCK = <?= (int) DOLOS_LOW_STOCK_THRESHOLD ?>;
  var CAMPUSES_OK = <?= $campusesConfigured ? 'true' : 'false' ?>;
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
    if (campusChosen() && campusError) { campusError.classList.add('hidden'); }
  }
  // ── Life Group: custom autocomplete (datalist is unreliable on iOS Safari) ──
  // Field stays free text — anything the orderer types is kept.
  var LIFE_GROUPS = <?= json_encode($lifeGroupsByCampus, JSON_UNESCAPED_SLASHES) ?>;
  var ALL_GROUPS = Object.keys(LIFE_GROUPS).reduce(function (acc, k) { return acc.concat(LIFE_GROUPS[k]); }, []);
  var lgInput = document.getElementById('lift-group-input');
  var lgList  = document.getElementById('lift-group-list');
  var lgHint  = document.getElementById('lift-group-hint');
  var lgError = document.getElementById('lift-group-error');
  var lgIdx   = -1;
  var NO_LIFE_GROUP = 'No Life Group';
  var noLgOpt = document.getElementById('no-life-group-option');

  function syncNoLifeGroupOption() {
    if (!noLgOpt) return;
    var on = lgInput.value.trim() === NO_LIFE_GROUP;
    SEL.forEach(function (c) { noLgOpt.classList.toggle(c, on); });
    UNSEL.forEach(function (c) { noLgOpt.classList.toggle(c, !on); });
  }
  function lgGroups() {
    var chosen = form.querySelector('.campus-radio:checked');
    return (chosen && LIFE_GROUPS[chosen.value]) ? LIFE_GROUPS[chosen.value] : [];
  }
  function lgCloseList() {
    lgList.classList.add('hidden');
    lgList.innerHTML = '';
    lgInput.setAttribute('aria-expanded', 'false');
    lgIdx = -1;
  }
  function lgOpenList() {
    var groups = lgGroups();
    if (!groups.length) { lgCloseList(); return; }
    var q = lgInput.value.trim().toLowerCase();
    var matches = groups.filter(function (g) { return g.toLowerCase().indexOf(q) !== -1; });
    if (!matches.length) { lgCloseList(); return; }
    lgList.innerHTML = '';
    matches.forEach(function (g) {
      var li = document.createElement('li');
      li.textContent = g;
      li.setAttribute('role', 'option');
      li.className = 'px-3 py-2 cursor-pointer hover:bg-indigo-50';
      li._val = g;
      lgList.appendChild(li);
    });
    lgList.classList.remove('hidden');
    lgInput.setAttribute('aria-expanded', 'true');
    lgIdx = -1;
  }
  function lgPick(val) {
    lgInput.value = val;
    lgCloseList();
    syncNoLifeGroupOption();
    if (lgError) lgError.classList.add('hidden');
    if (typeof recalc === 'function') recalc();
  }
  function lgHighlight(items) {
    items.forEach(function (it, i) { it.classList.toggle('bg-indigo-50', i === lgIdx); });
    if (lgIdx >= 0) { items[lgIdx].scrollIntoView({ block: 'nearest' }); }
  }

  lgInput.addEventListener('input', function () {
    syncNoLifeGroupOption();
    if (lgInput.value.trim() && lgError) lgError.classList.add('hidden');
    lgOpenList();
  });
  lgInput.addEventListener('focus', lgOpenList);
  lgInput.addEventListener('blur', function () { setTimeout(lgCloseList, 150); });
  lgInput.addEventListener('keydown', function (e) {
    var items = Array.prototype.slice.call(lgList.querySelectorAll('li'));
    if (e.key === 'Escape') { lgCloseList(); return; }
    if (!items.length) return;
    if (e.key === 'ArrowDown') { e.preventDefault(); lgIdx = Math.min(lgIdx + 1, items.length - 1); lgHighlight(items); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); lgIdx = Math.max(lgIdx - 1, 0); lgHighlight(items); }
    else if (e.key === 'Enter' && lgIdx >= 0) { e.preventDefault(); lgPick(items[lgIdx]._val); }
  });
  // pointerdown fires before the input's blur, on both touch and mouse.
  lgList.addEventListener('pointerdown', function (e) {
    var li = e.target.closest('li');
    if (!li) return;
    e.preventDefault();
    lgPick(li._val);
  });
  if (noLgOpt) {
    noLgOpt.addEventListener('click', function (e) {
      e.preventDefault();
      lgPick(NO_LIFE_GROUP);
    });
  }
  syncNoLifeGroupOption();

  function lgUpdateForCampus(userChanged) {
    var chosen = form.querySelector('.campus-radio:checked');
    var groups = lgGroups();
    if (!chosen) {
      lgHint.textContent = 'Choose your campus above to see its life groups, or type your own (max 20 characters). Required.';
    } else if (groups.length) {
      lgHint.textContent = 'Start typing to pick a ' + chosen.value + ' life group, enter your own, or choose “I don\'t join a Life Group” below.';
    } else {
      lgHint.textContent = 'Enter your life group name, or choose “I don\'t join a Life Group” below.';
    }
    // On an actual campus switch, drop a value that was a suggestion from the
    // previous campus (keep anything the orderer typed themselves, including No Life Group).
    if (userChanged && lgInput.value && lgInput.value !== NO_LIFE_GROUP
        && ALL_GROUPS.indexOf(lgInput.value) !== -1 && groups.indexOf(lgInput.value) === -1) {
      lgInput.value = '';
      syncNoLifeGroupOption();
    }
    lgCloseList();
  }

  form.querySelectorAll('.campus-radio').forEach(function (r) {
    r.addEventListener('change', function () { syncCampus(); lgUpdateForCampus(true); });
  });
  syncCampus();
  lgUpdateForCampus(false);

  // Submit flow: campus guard, then an order-review step before Stripe.
  var confirmed = false;
  var modal = document.getElementById('confirm-modal');
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
  // Dish name with English (Latin) runs a little smaller + lighter (mirrors dish_name_html in PHP).
  function dishNameHtml(name) {
    var s = String(name == null ? '' : name), out = '', re = /[\x20-\x7E]+/g, last = 0, m;
    while ((m = re.exec(s))) {
      out += esc(s.slice(last, m.index));
      var seg = m[0];
      if (/[A-Za-z]/.test(seg)) {
        var lead = seg[0] === ' ' ? ' ' : '', trail = seg.slice(-1) === ' ' ? ' ' : '';
        out += lead + '<span class="text-[0.85em] font-normal text-gray-500">' + esc(seg.trim()) + '</span>' + trail;
      } else {
        out += esc(seg);
      }
      last = re.lastIndex;
    }
    return out + esc(s.slice(last));
  }

  function buildSummary() {
    var f = function (n) { var el = form.querySelector('[name="' + n + '"]'); return el ? el.value.trim() : ''; };
    var campus = campusChosen() ? campusChosen().value : '';
    var lg = f('lift_group'), phone = f('phone');

    document.getElementById('sum-name').textContent = (f('first_name') + ' ' + f('last_name')).trim();
    document.getElementById('sum-email').textContent = f('email') || '(none — confirmation to church office)';
    document.getElementById('sum-campus').textContent = campus;
    document.getElementById('sum-lg').textContent = lg;
    document.getElementById('sum-lg-row').style.display = '';
    document.getElementById('sum-phone').textContent = phone;
    document.getElementById('sum-phone-row').style.display = phone ? '' : 'none';
    document.getElementById('sum-att-adults').textContent = f('attending_adults') || '0';
    document.getElementById('sum-att-children').textContent = f('attending_children') || '0';
    var adultNames = collectNames('adult_names[]');
    var childNames = collectNames('child_names[]');
    document.getElementById('sum-adult-names').textContent = adultNames.join(', ');
    document.getElementById('sum-adult-names-row').classList.toggle('hidden', !adultNames.length);
    document.getElementById('sum-child-names').textContent = childNames.join(', ');
    document.getElementById('sum-child-names-row').classList.toggle('hidden', !childNames.length);

    var rows = '', total = 0;
    form.querySelectorAll('input[data-qty]').forEach(function (h) {
      var code = h.dataset.qty;
      var qty = parseInt(h.value, 10) || 0;
      var cb = form.querySelector('.box-check[value="' + code + '"]');
      if (qty <= 0 || (cb && cb.disabled)) return;
      var sub = (PRICES[code] || 0) * qty;
      total += sub;
      rows += '<tr>'
        + '<td class="py-1 pr-3"><span class="font-semibold text-indigo-700">' + esc(code) + '</span> ' + dishNameHtml(BOX_NAMES[code] || '') + '</td>'
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
    if (!CAMPUSES_OK || !campusChosen()) {
      e.preventDefault();
      if (campusError) {
        campusError.classList.remove('hidden');
        campusError.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
      return;
    }
    if (!fieldVal('lift_group')) {
      e.preventDefault();
      if (lgError) {
        lgError.classList.remove('hidden');
        lgError.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
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

  function fieldVal(n) {
    var el = form.querySelector('[name="' + n + '"]');
    return el ? el.value.trim() : '';
  }
  function attendVal(name) {
    var el = form.querySelector('input[data-attend="' + name + '"]');
    return el ? (parseInt(el.value, 10) || 0) : 0;
  }
  function collectNames(fieldName) {
    return Array.prototype.map.call(
      form.querySelectorAll('input[name="' + fieldName + '"]'),
      function (el) { return el.value.trim(); }
    ).filter(Boolean);
  }
  function namesComplete(fieldName, count, minCount) {
    minCount = minCount == null ? 2 : minCount;
    if (count < minCount) return true;
    var inputs = form.querySelectorAll('input[name="' + fieldName + '"]');
    if (inputs.length !== count) return false;
    for (var i = 0; i < inputs.length; i++) {
      if (!inputs[i].value.trim()) return false;
    }
    return true;
  }
  function registrantName() {
    return (fieldVal('first_name') + ' ' + fieldVal('last_name')).replace(/\s+/g, ' ').trim();
  }
  function syncNameFields(listEl, count) {
    if (!listEl) return;
    var kind = listEl.dataset.nameList;
    var label = listEl.dataset.nameLabel || kind;
    var minCount = parseInt(listEl.dataset.nameMin || '2', 10);
    var fieldName = kind === 'adult' ? 'adult_names[]' : 'child_names[]';
    var existing = Array.prototype.map.call(
      listEl.querySelectorAll('input[type="text"]'),
      function (el) { return el.value; }
    );
    if (!existing.length && listEl._savedNames && listEl._savedNames.length) {
      existing = listEl._savedNames.slice();
    } else if (!existing.length && listEl.dataset.initialNames) {
      try { existing = JSON.parse(listEl.dataset.initialNames) || []; } catch (e) { existing = []; }
      listEl.dataset.initialNames = '[]';
    }
    if (count < minCount) {
      if (existing.length) listEl._savedNames = existing;
      listEl.innerHTML = '';
      listEl.classList.add('hidden');
      return;
    }
    listEl.classList.remove('hidden');
    listEl.innerHTML = '';
    var reg = registrantName();
    var prevReg = listEl._lastRegistrantName || '';
    for (var i = 0; i < count; i++) {
      var wrap = document.createElement('label');
      wrap.className = 'block';
      wrap.innerHTML =
        '<span class="text-xs font-medium text-gray-600">' + label + ' ' + (i + 1) + ' name <span class="text-red-600">*</span></span>' +
        '<input type="text" name="' + fieldName + '" required maxlength="100" autocomplete="name" ' +
        'class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" ' +
        'value="">';
      var input = wrap.querySelector('input');
      var val = existing[i] || '';
      // Adult 1 defaults from Your details (editable). Keep in sync until the user edits it.
      if (kind === 'adult' && i === 0) {
        if (!val.trim() || val.trim() === prevReg) {
          val = reg;
        }
      }
      if (val) input.value = val;
      listEl.appendChild(wrap);
    }
    if (kind === 'adult') listEl._lastRegistrantName = reg;
    listEl._savedNames = Array.prototype.map.call(
      listEl.querySelectorAll('input[type="text"]'),
      function (el) { return el.value; }
    );
  }
  function syncAdult1FromDetails() {
    var list = document.getElementById('adult-names');
    if (!list || list.classList.contains('hidden')) return;
    var input = list.querySelector('input[name="adult_names[]"]');
    if (!input) return;
    var reg = registrantName();
    var prev = list._lastRegistrantName || '';
    if (!input.value.trim() || input.value.trim() === prev) {
      input.value = reg;
    }
    list._lastRegistrantName = reg;
  }
  function syncAllNameFields() {
    syncNameFields(document.getElementById('adult-names'), attendVal('attending_adults'));
    syncNameFields(document.getElementById('child-names'), attendVal('attending_children'));
  }
  function detailsComplete() {
    if (!fieldVal('first_name') || !fieldVal('last_name')) return false;
    var email = fieldVal('email');
    var phone = fieldVal('phone');
    var emailOk = !!(email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email));
    var phoneOk = (phone.replace(/\D/g, '').length >= 10);
    if (email && !emailOk) return false;
    return emailOk || phoneOk;
  }
  function attendanceComplete() {
    var adults = attendVal('attending_adults');
    var children = attendVal('attending_children');
    if (adults + children < 1) return false;
    if (!namesComplete('adult_names[]', adults, 1)) return false;
    if (!namesComplete('child_names[]', children, 1)) return false;
    return true;
  }
  function campusComplete() {
    return CAMPUSES_OK && !!campusChosen() && fieldVal('lift_group') !== '';
  }
  function totalAttendance() {
    return attendVal('attending_adults') + attendVal('attending_children');
  }
  function totalBoxQty() {
    var total = 0;
    form.querySelectorAll('input[data-qty]').forEach(function (h) {
      var qty = parseInt(h.value, 10) || 0;
      var cb = form.querySelector('.box-check[value="' + h.dataset.qty + '"]');
      if (qty > 0 && cb && !cb.disabled) total += qty;
    });
    return total;
  }
  function boxesWithinAttendance() {
    return totalBoxQty() <= totalAttendance();
  }
  function formIsComplete(anyBox) {
    return detailsComplete() && attendanceComplete() && campusComplete()
      && !!anyBox && boxesWithinAttendance();
  }

  function setStepOpen(stepEl, open) {
    if (!stepEl) return;
    stepEl.classList.toggle('form-step-locked', !open);
    stepEl.setAttribute('aria-disabled', open ? 'false' : 'true');
    if ('inert' in stepEl) stepEl.inert = !open;
  }

  // When Your details first becomes complete: treat the registrant as Adult 1
  // (count 1 + name filled from first/last), as if they entered it themselves.
  var detailsWasComplete = false;
  function ensureRegistrantAsAdult1() {
    var adults = attendVal('attending_adults');
    var children = attendVal('attending_children');
    if (adults + children === 0) {
      if (typeof attendSetters !== 'undefined' && attendSetters.attending_adults) {
        attendSetters.attending_adults(1, true);
      } else {
        var adultsH = form.querySelector('input[data-attend="attending_adults"]');
        if (adultsH) {
          adultsH.value = '1';
          var wrap = adultsH.closest('[data-attend-stepper]');
          if (wrap) {
            var numEl = wrap.querySelector('[data-attend-num]');
            if (numEl) numEl.textContent = '1';
          }
        }
      }
      adults = 1;
    }
    if (adults >= 1) {
      syncNameFields(document.getElementById('adult-names'), adults);
      syncAdult1FromDetails();
      // Ensure children + is enabled now that an adult is present.
      if (typeof attendSetters !== 'undefined' && attendSetters.attending_children) {
        attendSetters.attending_children(attendVal('attending_children'), true);
      }
    }
  }
  function syncFormSteps() {
    // Unlock strictly in order: details → attendance → campus → boxes/checkout.
    var dOk = detailsComplete();
    if (dOk && !detailsWasComplete) {
      detailsWasComplete = true;
      ensureRegistrantAsAdult1();
    } else if (!dOk) {
      detailsWasComplete = false;
    }
    dOk = detailsComplete();
    var aOk = dOk && attendanceComplete();
    var cOk = aOk && campusComplete();
    var overAttend = totalBoxQty() > totalAttendance();
    var boxQty = totalBoxQty();
    var people = totalAttendance();
    var overMsg = document.getElementById('boxes-over-attend');
    if (overMsg) {
      overMsg.classList.toggle('hidden', !(cOk && overAttend));
    }
    var sumEl = document.getElementById('boxes-attend-summary');
    var sumBoxes = document.getElementById('boxes-attend-boxes');
    var sumPeople = document.getElementById('boxes-attend-people');
    if (sumEl && sumBoxes && sumPeople) {
      sumBoxes.textContent = String(boxQty);
      sumPeople.textContent = String(people);
      sumEl.classList.toggle('hidden', !cOk);
      sumEl.classList.toggle('text-red-600', overAttend);
      sumEl.classList.toggle('font-medium', overAttend);
      sumEl.classList.toggle('text-gray-600', !overAttend);
    }
    setStepOpen(form.querySelector('[data-form-step="details"]'), true);
    setStepOpen(form.querySelector('[data-form-step="attendance"]'), dOk);
    setStepOpen(form.querySelector('[data-form-step="campus"]'), aOk);
    setStepOpen(form.querySelector('[data-form-step="boxes"]'), cOk);
    setStepOpen(form.querySelector('[data-form-step="checkout"]'), cOk);
  }

  // Attendance steppers + name fields (adults / children from 1+).
  // Children require at least one adult.
  var attendSetters = {};
  form.querySelectorAll('[data-attend-stepper]').forEach(function (wrap) {
    var hidden = wrap.querySelector('input[data-attend]');
    var numEl  = wrap.querySelector('[data-attend-num]');
    var dec    = wrap.querySelector('[data-attend-btn="dec"]');
    var inc    = wrap.querySelector('[data-attend-btn="inc"]');
    var kind   = hidden.dataset.attend;
    var minV   = parseInt(wrap.dataset.attendMin || '0', 10);
    var maxV   = parseInt(wrap.dataset.attendMax || '50', 10);

    function refreshButtons(n) {
      dec.disabled = n <= minV;
      if (kind === 'attending_children') {
        inc.disabled = n >= maxV || attendVal('attending_adults') < 1;
      } else {
        inc.disabled = n >= maxV;
      }
    }
    function setAttend(n, skipRecalc) {
      n = Math.max(minV, Math.min(maxV, n | 0));
      if (kind === 'attending_children' && attendVal('attending_adults') < 1) {
        n = 0;
      }
      hidden.value = String(n);
      numEl.textContent = String(n);
      refreshButtons(n);
      if (kind === 'attending_adults') {
        syncNameFields(document.getElementById('adult-names'), n);
        if (n < 1 && attendSetters.attending_children) {
          attendSetters.attending_children(0, true);
        } else if (attendSetters.attending_children) {
          // Re-enable/disable children + based on adult count.
          var ch = attendVal('attending_children');
          var chWrap = form.querySelector('[data-attend-stepper="attending_children"]');
          if (chWrap) {
            var chInc = chWrap.querySelector('[data-attend-btn="inc"]');
            var chDec = chWrap.querySelector('[data-attend-btn="dec"]');
            var chMax = parseInt(chWrap.dataset.attendMax || '50', 10);
            if (chDec) chDec.disabled = ch <= 0;
            if (chInc) chInc.disabled = ch >= chMax || n < 1;
          }
        }
      } else if (kind === 'attending_children') {
        syncNameFields(document.getElementById('child-names'), n);
      }
      if (!skipRecalc) recalc();
    }
    attendSetters[kind] = setAttend;
    dec.addEventListener('click', function () { setAttend((parseInt(hidden.value, 10) || 0) - 1); });
    inc.addEventListener('click', function () { setAttend((parseInt(hidden.value, 10) || 0) + 1); });
    var n = Math.max(minV, Math.min(maxV, parseInt(hidden.value, 10) || minV));
    if (kind === 'attending_children' && attendVal('attending_adults') < 1) n = 0;
    hidden.value = String(n);
    numEl.textContent = String(n);
    refreshButtons(n);
  });
  syncAllNameFields();
  // Final pass so children + reflects adult count after both steppers exist.
  if (attendSetters.attending_adults) {
    attendSetters.attending_adults(attendVal('attending_adults'), true);
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
    syncFormSteps();
    payBtn.disabled = !formIsComplete(any);
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
      if (info.sold_out) {
        label.textContent = 'Sold out';
        label.className = 'block text-xs min-h-[1em] text-red-600 font-semibold';
        cb.disabled = true;
        wrap.dataset.allow = '0';
        if (wrap._setQty) wrap._setQty(0);
      } else {
        label.textContent = info.remaining <= LOW_STOCK ? (info.remaining + ' left') : '';
        label.className = 'block text-xs min-h-[1em] text-amber-600 font-medium';
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

  // Whole box card toggles selection. Clicks on the qty stepper, or on the
  // checkbox/label itself (which the browser already toggles), are left alone.
  form.querySelectorAll('[data-box-row]').forEach(function (row) {
    var cb = row.querySelector('.box-check');
    if (!cb) return;
    row.addEventListener('click', function (e) {
      if (cb.disabled) return;
      if (e.target.closest('[data-stepper]')) return;
      if (e.target.closest('label')) return;
      cb.checked = !cb.checked;
      cb.dispatchEvent(new Event('change', { bubbles: true }));
    });
  });

  form.addEventListener('change', recalc);
  form.addEventListener('input', function (e) {
    var t = e.target;
    if (t && (t.name === 'first_name' || t.name === 'last_name')) {
      syncAdult1FromDetails();
    }
    recalc();
  });
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
