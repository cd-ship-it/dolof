<?php
/**
 * Public ordering form. Also re-rendered by create-checkout.php on error, which
 * pre-populates $form_errors (string[]) and $old (assoc: field => value,
 * $old['attendees'] as first/last/box/child rows).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/boxes.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/layout.php';

auth_start_session();

$form_errors = $form_errors ?? [];
$old         = $old ?? [];
$boxes       = boxes_with_remaining($pdo);
$adminOpen   = ordering_is_open($pdo);
$windowOpen  = ordering_within_window();
$checkoutOk  = $adminOpen && $windowOpen;
$orderStart  = ordering_window_start();
$orderEnd    = ordering_window_end();
$maxQty      = DOLOS_MAX_QTY_PER_BOX;
$cancelled   = isset($_GET['cancelled']);
$checkoutModeElements = checkout_mode_is_elements($pdo);

if (!empty($_SESSION['flash_error'])) {
    $form_errors[] = (string) $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// Campuses: single source = data/life-groups.json keys
$campuses           = campuses();
$campusesConfigured = $campuses !== [];
$lifeGroupsByCampus = life_groups_by_campus();
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

<?php
  // Banner + countdown for the .env ordering window (form is always browsable).
  $nowTs = time();
  $bannerClass = 'border-amber-300 bg-amber-50 text-amber-900';
  $bannerTitle = 'Ordering is not open yet';
  $bannerBody  = $orderStart
      ? 'You can explore the form now. Checkout opens ' . ordering_format_pt($orderStart) . '.'
      : 'You can explore the form now. Checkout is not available yet.';
  // Deadline countdown only after opening time. Before that, show the deadline time only.
  $showDeadlineCountdown = $orderEnd && $orderStart && $nowTs >= $orderStart->getTimestamp() && $nowTs <= $orderEnd->getTimestamp();
  $countdownTarget = $showDeadlineCountdown ? $orderEnd : null;
  $countdownLabel  = '倒數';
  if ($windowOpen) {
      $bannerClass = 'border-emerald-300 bg-emerald-50 text-emerald-900';
      $bannerTitle = 'Ordering is open';
      $bannerBody  = $orderEnd
          ? 'Place your order anytime until ' . ordering_format_pt($orderEnd) . '.'
          : 'You can place your order now.';
  } elseif ($orderEnd && $nowTs > $orderEnd->getTimestamp()) {
      $bannerClass = 'border-red-300 bg-red-50 text-red-900';
      $bannerTitle = 'Ordering has closed';
      $bannerBody  = 'You can still look around, but checkout is no longer available. Window ended '
          . ordering_format_pt($orderEnd) . '.';
      $countdownTarget = null;
      $countdownLabel  = '';
  }
  if (!$adminOpen) {
      $bannerClass = 'border-red-300 bg-red-50 text-red-900';
      $bannerTitle = 'Ordering is temporarily closed';
      $bannerBody  = 'You can explore the form, but checkout is paused by the church office.';
      $countdownTarget = null;
      $countdownLabel  = '';
  }

  $countdownText = '';
  if ($countdownTarget) {
      $remain = max(0, $countdownTarget->getTimestamp() - $nowTs);
      $d = intdiv($remain, 86400);
      $h = intdiv($remain % 86400, 3600);
      $m = intdiv($remain % 3600, 60);
      $s = $remain % 60;
      $parts = [];
      if ($d > 0) {
          $parts[] = $d . 'd';
      }
      $parts[] = sprintf('%02dh', $h);
      $parts[] = sprintf('%02dm', $m);
      $parts[] = sprintf('%02ds', $s);
      $countdownText = $countdownLabel . ' ' . implode(' ', $parts);
  }
  $beforeStart = $orderStart && $nowTs < $orderStart->getTimestamp();
  // Before opening day: form is fillable like during the window; only Continue stays locked.
  $formBrowseOk = $beforeStart || $checkoutOk;
  $progressiveSteps = ORDER_FORM_PROGRESSIVE_STEPS;
  $stepUnlocked = static fn (): bool => $formBrowseOk && !$progressiveSteps;
?>


<?php if ($orderStart): ?>
<div id="ordering-opens-banner" class="card mb-6 border-emerald-300 bg-emerald-50 text-emerald-900<?= $beforeStart ? '' : ' hidden' ?>">
  <p class="font-semibold text-base">Accepting order on <?= e(ordering_format_pt($orderStart)) ?></p>
</div>
<?php endif; ?>
<div id="ordering-window-banner" class="card mb-6 <?= e($bannerClass) ?><?= $beforeStart ? ' hidden' : '' ?>"
     data-start-ms="<?= $orderStart ? e((string) ($orderStart->getTimestamp() * 1000)) : '' ?>"
     data-end-ms="<?= $orderEnd ? e((string) ($orderEnd->getTimestamp() * 1000)) : '' ?>"
     data-admin-open="<?= $adminOpen ? '1' : '0' ?>">
  <p class="font-semibold text-base" id="ordering-banner-title"><?= e($bannerTitle) ?></p>
</div>

<?php if ($orderEnd): ?>
    <div class="mt-3 flex items-start gap-3 rounded-lg border-2 border-red-700 bg-red-600 px-3 py-3 text-white">
      <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white text-lg font-bold leading-none text-red-600" aria-hidden="true">!</span>
      <div>
        <p class="text-sm font-semibold">截止日期: <?= e(ordering_format_pt($orderEnd)) ?></p>
        <p class="mt-1 text-sm font-medium tabular-nums<?= $countdownText === '' ? ' hidden' : '' ?>"
           id="ordering-countdown"><?= e($countdownText) ?></p>
      </div>
    </div>
  <?php else: ?>
    <p class="hidden" id="ordering-countdown"></p>
  <?php endif; ?>

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

  <div class="flex flex-wrap gap-3">
    <button type="button" id="font-smaller"
            class="rounded-md border-2 border-gray-300 bg-white px-4 py-2 text-sm font-large text-gray-800 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
        縮小字體
    </button>
    <button type="button" id="font-bigger"
            class="rounded-md border-2 border-gray-300 bg-white px-4 py-2 text-sm font-large text-gray-800 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
      放大字體
    </button>
  </div>

  <div class="card space-y-4 transition-opacity duration-200<?= $formBrowseOk ? '' : ' form-step-locked' ?>" data-form-step="details"<?= $formBrowseOk ? '' : ' aria-disabled="true"' ?>>
    <h2 class="text-2xl font-semibold text-gray-900">登記人/小組組長/家長</h2>
    <div class="grid sm:grid-cols-2 gap-4">
      <label class="block">
        <span class="text-xl font-medium text-gray-700">名 First name <span class="text-red-600">*</span></span>
        <input type="text" name="first_name" required maxlength="100" value="<?= e($old['first_name'] ?? '') ?>"
               class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
      </label>
      <label class="block">
        <span class="text-xl font-medium text-gray-700">姓 Last name <span class="text-red-600">*</span></span>
        <input type="text" name="last_name" required maxlength="100" value="<?= e($old['last_name'] ?? '') ?>"
               class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
      </label>
    </div>
    <hr class="border-gray-200">
    <p class="text-xl font-medium text-gray-700"><span class="text-red-600">*</span> 請輸入電郵或電話號碼. </p>
    <!--<p class="text-sm  font-medium text-gray-700"><span class="text-red-600">*</span> Email or phone is required (at least one). Email is recommanded so you can receive an electronic receipt.</p>-->
    <div class="flex flex-col sm:flex-row sm:items-end gap-4">
      <label class="block flex-1">
        <span class="text-xl font-medium text-gray-700">Email</span>
        <input type="email" name="email" maxlength="200" value="<?= e($old['email'] ?? '') ?>"
               class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
      </label>
      <span class="text-center text-xl font-semibold text-gray-500 sm:pb-2.5" aria-hidden="true">或</span>
      <label class="block flex-1">
        <span class="text-xl font-medium text-gray-700">Phone (建議使用電郵以便收到電子收據。)</span>
        <input type="tel" name="phone" maxlength="50" value="<?= e($old['phone'] ?? '') ?>"
               class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
      </label>
    </div>
    <p id="contact-error" class="hidden text-sm font-medium text-red-600">Please enter a valid email or a phone number.</p>
  </div>
  <?php
    $attendeesOld = [];
    if (isset($old['attendees']) && is_array($old['attendees'])) {
        foreach ($old['attendees'] as $a) {
            if (!is_array($a)) {
                continue;
            }
            $attendeesOld[] = [
                'first' => (string) ($a['first'] ?? ''),
                'last'  => (string) ($a['last'] ?? ''),
                'box'   => (string) ($a['box'] ?? ''),
                'child' => !empty($a['child']),
            ];
        }
    } else {
        foreach (array_values((array) ($old['adult_attendees'] ?? [])) as $a) {
            if (!is_array($a)) {
                continue;
            }
            $a['child'] = false;
            $attendeesOld[] = $a;
        }
        foreach (array_values((array) ($old['child_attendees'] ?? [])) as $a) {
            if (!is_array($a)) {
                continue;
            }
            $a['child'] = true;
            $attendeesOld[] = $a;
        }
    }
    $attendeesOld = array_slice($attendeesOld, 0, 50);
  ?>
  <div class="card space-y-4 transition-opacity duration-200<?= $stepUnlocked() ? '' : ' form-step-locked' ?>" data-form-step="campus"<?= $stepUnlocked() ? '' : ' aria-disabled="true"' ?>>
    <h2 class="text-xl font-semibold text-gray-900">會堂 <span class="text-red-600">*</span></h2>

    <?php if (!$campusesConfigured): ?>
      <div class="rounded-lg border border-red-300 bg-red-50 px-3 py-3 text-sm text-red-700">
        <p class="font-semibold">Campus options are not configured.</p>
        <p class="mt-1">Please contact the church office — online ordering cannot continue until campuses are defined.</p>
      </div>
    <?php else: ?>
    <div class="grid grid-cols-2 gap-3">
      <?php foreach ($campuses as $c): $wide = strlen($c) > 16; ?>
        <label class="campus-option relative flex cursor-pointer items-center justify-center rounded-lg border-2 px-2 py-2 text-center font-medium text-sm transition <?= $wide ? 'col-span-2' : '' ?>
                      <?= $oldCampus === $c ? 'border-indigo-600 bg-indigo-50 text-indigo-800 ring-2 ring-indigo-300' : 'border-amber-200 bg-amber-50/60 text-gray-600 hover:border-amber-300' ?>">
          <input type="radio" name="campus" value="<?= e($c) ?>" class="sr-only campus-radio" <?= $oldCampus === $c ? 'checked' : '' ?>>
          <?= e($c) ?>
        </label>
      <?php endforeach; ?>
    </div>
    <p id="campus-error" class="hidden text-sm font-medium text-red-600">Please choose a campus to continue.</p>
    <?php endif; ?>

    <div class="block space-y-2">
      <label for="lift-group-input" class="font-semibold text-gray-900">Lift Group Name <span class="text-red-600">*</span></label>
      <?php
        $noLgLabel = "I don't join a Life Group";
        $noLgValue = 'No Life Group';
        $noLgSelected = ($old['lift_group'] ?? '') === $noLgValue;
      ?>
      <div class="relative mt-1">
        <input type="text" name="lift_group" id="lift-group-input"
               required maxlength="20" autocomplete="one-time-code" autocapitalize="off" autocorrect="off" spellcheck="false"
               role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="lift-group-list"
               value="<?= e($old['lift_group'] ?? '') ?>"
               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500<?= $noLgSelected ? ' lg-field-locked' : '' ?>"
               <?= $noLgSelected ? 'readonly' : '' ?>
               <?= $campusesConfigured ? '' : 'disabled' ?>>
        <ul id="lift-group-list" role="listbox" hidden
            class="absolute z-20 left-0 right-0 mt-1 max-h-56 overflow-auto rounded-md border border-gray-200 bg-white text-sm shadow-lg"></ul>
      </div>
      <!-- <span class="text-xs text-gray-500 block" id="lift-group-hint">Choose your campus above to see its life groups, or type your own (max 20 characters).</span> -->
      <label class="mt-2 flex items-center gap-2 cursor-pointer select-none text-sm text-gray-800">
        <input type="checkbox" id="no-life-group-option" class="h-5 w-5 rounded border-gray-400 text-indigo-600 focus:ring-indigo-500"
               <?= $noLgSelected ? 'checked' : '' ?>
               <?= $campusesConfigured ? '' : 'disabled' ?>>
        <span><?= e($noLgLabel) ?></span>
      </label>
      <!-- <p id="lift-group-error" class="hidden text-sm font-medium text-red-600">Please enter a Lift Group Name, or check “I don't join a Life Group”.</p> -->
    </div>
  </div>
  <div class="card space-y-4 transition-opacity duration-200<?= $stepUnlocked() ? '' : ' form-step-locked' ?>" data-form-step="attendance"<?= $stepUnlocked() ? '' : ' aria-disabled="true"' ?>>
    <h2 class="text-xl font-semibold text-gray-900">出席者名單<span class="text-red-600">*</span></h2>
    <p class="text-md ">每份$15連稅。<br>可以只填名字留位但不點餐，不另收費。
    </p>
    <div id="attendees" class="space-y-4"
         data-initial-attendees="<?= e(json_encode($attendeesOld, JSON_UNESCAPED_UNICODE)) ?>"></div>
    <button type="button" id="add-attendee" class="btn-secondary w-full sm:w-auto hidden">
      再加一位成人或孩童
    </button>
    <p id="attendance-adult-error" class="hidden text-sm font-medium text-red-600">At least one person who is not 12 or under is required.</p>
    <input type="hidden" name="attending_adults" value="0" data-attend="attending_adults">
    <input type="hidden" name="attending_children" value="0" data-attend="attending_children">
    <!-- <div class="col-span-full flex flex-col items-center text-center gap-1 py-1">
      <img src="<?= e(APP_URL) ?>/img/koi-palace.webp" alt="Koi Palace 鯉魚門" class="h-16 w-auto">
      <p class="text-xs font-medium text-black-400">以上四款餐點由Milpitas鯉魚門提供</p>
      <p class="text-xs font-medium text-red-600">** 齋菜餐點由另一食肆提供 **</p>
    </div> -->
  </div>
  <?php if ($orderStart): ?>
<div id="ordering-opens-banner" class="card mb-6 border-emerald-300 bg-emerald-50 text-emerald-900<?= $beforeStart ? '' : ' hidden' ?>">
  <p class="font-semibold text-base">Accepting order on <?= e(ordering_format_pt($orderStart)) ?></p>
</div>
<?php endif; ?>
  <div class="card flex items-center justify-between transition-opacity duration-200<?= $stepUnlocked() ? '' : ' form-step-locked' ?>" data-form-step="checkout"<?= $stepUnlocked() ? '' : ' aria-disabled="true"' ?>>
    <div>
      <span class="text-sm text-gray-500">Order total</span>
      <div class="text-2xl font-bold text-indigo-900" id="order-total">$0.00</div>
    </div>
    <button type="submit" class="btn-primary" id="pay-btn" disabled
            data-checkout-ok="<?= $checkoutOk ? '1' : '0' ?>"
            title="<?= !$campusesConfigured ? 'Campus options are not configured' : ($checkoutOk ? '' : 'Checkout is only available during the ordering window') ?>">Continue</button>
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
      <div><span class="text-gray-500">Attendance:</span> <span id="sum-att-count"></span></div>
      <div id="sum-attendee-names-row" class="hidden pl-3 text-gray-600"><span id="sum-attendee-names"></span></div>
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

    <p id="confirm-pay-note" class="text-xs text-gray-500">Next you'll continue to our payment page to pay this amount by card. Your order is confirmed only after payment succeeds.</p>

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
  var BOXES = <?= json_encode(array_values(array_map(fn($b) => [
      'code' => $b['code'],
      'name' => $b['name'],
      'sold_out' => (bool) $b['sold_out'],
  ], $boxes)), JSON_UNESCAPED_UNICODE) ?>;
  var LOW_STOCK = <?= (int) DOLOS_LOW_STOCK_THRESHOLD ?>;
  var remainingInfo = {};
  var CAMPUSES_OK = <?= $campusesConfigured ? 'true' : 'false' ?>;
  var checkoutOk = <?= $checkoutOk ? 'true' : 'false' ?>;
  var checkoutModeElements = <?= $checkoutModeElements ? 'true' : 'false' ?>;
  var formBrowseOk = <?= $formBrowseOk ? 'true' : 'false' ?>;
  var progressiveSteps = <?= $progressiveSteps ? 'true' : 'false' ?>;
  var form = document.getElementById('order-form');
  var totalEl = document.getElementById('order-total');
  var payBtn = document.getElementById('pay-btn');

  // Root rem scale (matches css/input.css default of 120%). Caps so text-xl
  // labels land around Tailwind text-5xl — usable max for this form.
  (function initFontScale() {
    var STEPS = [100, 120, 140, 160, 180, 200];
    var KEY = 'dolos-font-pct';
    var DEFAULT = 120;
    var smallerBtn = document.getElementById('font-smaller');
    var biggerBtn = document.getElementById('font-bigger');
    function idxFromStored() {
      var pct = parseInt(localStorage.getItem(KEY) || String(DEFAULT), 10);
      var i = STEPS.indexOf(pct);
      return i >= 0 ? i : STEPS.indexOf(DEFAULT);
    }
    function apply(i) {
      i = Math.max(0, Math.min(STEPS.length - 1, i));
      var pct = STEPS[i];
      document.documentElement.style.fontSize = pct + '%';
      try { localStorage.setItem(KEY, String(pct)); } catch (e) {}
      if (smallerBtn) smallerBtn.disabled = i === 0;
      if (biggerBtn) biggerBtn.disabled = i === STEPS.length - 1;
    }
    apply(idxFromStored());
    if (smallerBtn) smallerBtn.addEventListener('click', function () { apply(idxFromStored() - 1); });
    if (biggerBtn) biggerBtn.addEventListener('click', function () { apply(idxFromStored() + 1); });
  })();

  // Live US phone formatting: (123) 456-7890 — register early so later script
  // errors cannot skip it.
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
    var reformatPhone = function () { phoneEl.value = formatPhone(phoneEl.value); };
    phoneEl.addEventListener('input', reformatPhone);
    phoneEl.addEventListener('blur', reformatPhone);
    reformatPhone();
  }

  // Ordering window banner + live countdown (Continue stays locked outside the window).
  (function setupOrderingWindow() {
    var banner = document.getElementById('ordering-window-banner');
    var opensBanner = document.getElementById('ordering-opens-banner');
    var titleEl = document.getElementById('ordering-banner-title');
    var bodyEl = document.getElementById('ordering-banner-body');
    var cdEl = document.getElementById('ordering-countdown');
    if (!banner || !titleEl || !cdEl) return;

    function readMs(attr) {
      var raw = banner.getAttribute(attr);
      if (!raw) return NaN;
      var n = Number(raw);
      return isFinite(n) ? n : NaN;
    }
    var startMs = readMs('data-start-ms');
    var endMs = readMs('data-end-ms');
    var adminOpen = banner.getAttribute('data-admin-open') === '1';

    function fmtPt(ms) {
      if (!isFinite(ms)) return '';
      try {
        return new Intl.DateTimeFormat('en-US', {
          timeZone: 'America/Los_Angeles',
          month: 'short', day: 'numeric', year: 'numeric',
          hour: 'numeric', minute: '2-digit'
        }).format(new Date(ms)) + ' PT';
      } catch (err) {
        return new Date(ms).toLocaleString();
      }
    }
    function pad(n) { return n < 10 ? '0' + n : String(n); }
    function fmtRemain(ms) {
      var s = Math.max(0, Math.floor(ms / 1000));
      var d = Math.floor(s / 86400); s -= d * 86400;
      var h = Math.floor(s / 3600); s -= h * 3600;
      var m = Math.floor(s / 60); s -= m * 60;
      var parts = [];
      if (d > 0) parts.push(d + 'd');
      parts.push(pad(h) + 'h', pad(m) + 'm', pad(s) + 's');
      return parts.join(' ');
    }
    function setBannerClasses(kind) {
      banner.className = 'card mb-6 ' + ({
        soon: 'border-amber-300 bg-amber-50 text-amber-900',
        open: 'border-emerald-300 bg-emerald-50 text-emerald-900',
        closed: 'border-red-300 bg-red-50 text-red-900'
      }[kind] || 'border-amber-300 bg-amber-50 text-amber-900');
    }
    function hideDeadlineCountdown() {
      cdEl.textContent = '';
      cdEl.classList.add('hidden');
    }
    function showDeadlineCountdown(remainMs) {
      cdEl.textContent = 'Deadline in ' + fmtRemain(remainMs);
      cdEl.classList.remove('hidden');
    }
    function tick() {
      var now = Date.now();
      var within = true;
      if (isFinite(startMs) && now < startMs) within = false;
      if (isFinite(endMs) && now > endMs) within = false;
      checkoutOk = adminOpen && within;
      formBrowseOk = (isFinite(startMs) && now < startMs) || checkoutOk;
      if (payBtn) payBtn.setAttribute('data-checkout-ok', checkoutOk ? '1' : '0');

      if (opensBanner) opensBanner.classList.toggle('hidden', !(isFinite(startMs) && now < startMs));

      if (isFinite(startMs) && now < startMs) {
        banner.classList.add('hidden');
        hideDeadlineCountdown();
      } else if (!adminOpen) {
        setBannerClasses('closed');
        titleEl.textContent = 'Ordering is temporarily closed';
        if (bodyEl) bodyEl.textContent = 'You can explore the form, but checkout is paused by the church office.';
        hideDeadlineCountdown();
      } else if (isFinite(endMs) && now > endMs) {
        setBannerClasses('closed');
        titleEl.textContent = 'Ordering has closed';
        if (bodyEl) bodyEl.textContent = 'You can still look around, but checkout is no longer available. Window ended ' + fmtPt(endMs) + '.';
        hideDeadlineCountdown();
      } else {
        setBannerClasses('open');
        titleEl.textContent = 'Ordering is open';
        if (bodyEl) {
          bodyEl.textContent = isFinite(endMs)
            ? 'Place your order anytime until ' + fmtPt(endMs) + '.'
            : 'You can place your order now.';
        }
        if (isFinite(endMs)) {
          showDeadlineCountdown(endMs - now);
        } else {
          hideDeadlineCountdown();
        }
      }

      try { if (typeof recalc === 'function') recalc(); } catch (err) {}
    }
    tick();
    setInterval(tick, 1000);
  })();

  // Campus radio: highlight the chosen card.
  var campusError = document.getElementById('campus-error');
  var SEL = 'border-indigo-600 bg-indigo-50 text-indigo-800 ring-2 ring-indigo-300'.split(' ');
  var UNSEL = 'border-amber-200 bg-amber-50/60 text-gray-600 hover:border-amber-300'.split(' ');
  function campusChosen() { return form.querySelector('.campus-radio:checked'); }
  function syncCampus() {
    form.querySelectorAll('.campus-option').forEach(function (opt) {
      var on = opt.querySelector('.campus-radio').checked;
      SEL.forEach(function (c) { opt.classList.toggle(c, on); });
      UNSEL.forEach(function (c) { opt.classList.toggle(c, !on); });
    });
    if (campusChosen() && campusError) { campusError.classList.add('hidden'); }
  }
  // ── Life Group: campus autocomplete + “No Life Group” checkbox ──
  // Custom list (not <datalist>) — more reliable on iOS Safari. Free text still allowed.
  var LIFE_GROUPS = <?= json_encode($lifeGroupsByCampus, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  var ALL_GROUPS = Object.keys(LIFE_GROUPS).reduce(function (acc, k) { return acc.concat(LIFE_GROUPS[k] || []); }, []);
  var lgInput = document.getElementById('lift-group-input');
  var lgList  = document.getElementById('lift-group-list');
  var lgHint  = document.getElementById('lift-group-hint');
  var lgError = document.getElementById('lift-group-error');
  var lgIdx   = -1;
  var NO_LIFE_GROUP = 'No Life Group';
  var noLgOpt = document.getElementById('no-life-group-option');

  function lgLocked() {
    return !!(lgInput && (lgInput.readOnly || lgInput.disabled || (noLgOpt && noLgOpt.checked)));
  }
  function lgGroups() {
    var chosen = form.querySelector('.campus-radio:checked');
    return (chosen && LIFE_GROUPS[chosen.value]) ? LIFE_GROUPS[chosen.value] : [];
  }
  function lgCloseList() {
    if (!lgList || !lgInput) return;
    lgList.hidden = true;
    lgList.innerHTML = '';
    lgInput.setAttribute('aria-expanded', 'false');
    lgIdx = -1;
  }
  function lgOpenList() {
    if (!lgList || !lgInput || lgLocked()) { lgCloseList(); return; }
    // Only after the user has typed at least one character.
    var raw = lgInput.value;
    if (raw.length < 1) { lgCloseList(); return; }
    var q = raw.trim().toLowerCase();
    if (!q.length) { lgCloseList(); return; }
    var groups = lgGroups();
    if (!groups.length) { lgCloseList(); return; }
    var matches = groups.filter(function (g) { return g.toLowerCase().indexOf(q) === 0; });
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
    lgList.hidden = false;
    lgInput.setAttribute('aria-expanded', 'true');
    lgIdx = -1;
  }
  function lgPick(val) {
    if (!lgInput) return;
    lgInput.value = val;
    lgCloseList();
    if (lgError) lgError.classList.add('hidden');
    if (typeof recalc === 'function') recalc();
  }
  function lgHighlight(items) {
    items.forEach(function (it, i) { it.classList.toggle('bg-indigo-50', i === lgIdx); });
    if (lgIdx >= 0) { items[lgIdx].scrollIntoView({ block: 'nearest' }); }
  }
  function lgUpdateForCampus(userChanged) {
    if (!lgHint) return;
    var chosen = form.querySelector('.campus-radio:checked');
    var groups = lgGroups();
    if (!chosen) {
      // lgHint.textContent = 'Choose your campus above to see its life groups, or type your own (max 20 characters).';
    } else if (groups.length) {
      // lgHint.textContent = 'Start typing to pick a ' + chosen.value + ' life group, or enter your own (max 20 characters).';
    } else {
      // lgHint.textContent = 'Enter your life group name (max 20 characters).';
    }
    // On campus switch, drop a prior campus's suggestion (keep free-typed values).
    if (userChanged && lgInput && !lgLocked() && lgInput.value
        && ALL_GROUPS.indexOf(lgInput.value) !== -1 && groups.indexOf(lgInput.value) === -1) {
      lgInput.value = '';
      if (typeof recalc === 'function') recalc();
    }
    lgCloseList();
  }

  function applyNoLifeGroup(on) {
    if (!lgInput) return;
    if (on) {
      lgInput.value = NO_LIFE_GROUP;
      lgInput.readOnly = true;
      lgInput.classList.add('lg-field-locked');
      lgCloseList();
    } else {
      if (lgInput.value.trim() === NO_LIFE_GROUP) lgInput.value = '';
      lgInput.readOnly = false;
      lgInput.classList.remove('lg-field-locked');
    }
    if (lgInput.value.trim() && lgError) lgError.classList.add('hidden');
    if (typeof recalc === 'function') recalc();
  }

  if (lgInput && lgList) {
    lgCloseList();
    lgInput.addEventListener('input', function () {
      if (noLgOpt && noLgOpt.checked && lgInput.value.trim() !== NO_LIFE_GROUP) {
        noLgOpt.checked = false;
        lgInput.readOnly = false;
        lgInput.classList.remove('lg-field-locked');
      }
      if (lgInput.value.trim() && lgError) lgError.classList.add('hidden');
      if (lgInput.value.length >= 1) lgOpenList();
      else lgCloseList();
      if (typeof recalc === 'function') recalc();
    });
    // Do not open on focus — only after the user types.
    lgInput.addEventListener('blur', function () { setTimeout(lgCloseList, 150); });
    lgInput.addEventListener('keydown', function (e) {
      var items = Array.prototype.slice.call(lgList.querySelectorAll('li'));
      if (e.key === 'Escape') { lgCloseList(); return; }
      if (!items.length) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); lgIdx = Math.min(lgIdx + 1, items.length - 1); lgHighlight(items); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); lgIdx = Math.max(lgIdx - 1, 0); lgHighlight(items); }
      else if (e.key === 'Enter' && lgIdx >= 0) { e.preventDefault(); lgPick(items[lgIdx]._val); }
    });
    lgList.addEventListener('pointerdown', function (e) {
      var li = e.target.closest('li');
      if (!li) return;
      e.preventDefault();
      lgPick(li._val);
    });
  }
  if (noLgOpt) {
    noLgOpt.addEventListener('change', function () {
      applyNoLifeGroup(!!noLgOpt.checked);
    });
    if (noLgOpt.checked) applyNoLifeGroup(true);
  }

  form.querySelectorAll('.campus-radio').forEach(function (r) {
    r.addEventListener('change', function () {
      syncCampus();
      lgUpdateForCampus(true);
      if (typeof recalc === 'function') recalc();
      if (lgInput && !lgInput.disabled) {
        lgInput.focus();
      }
    });
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

  function lunchChoiceLabel(code) {
    if (code === 'none') return '留位但不點餐';
    return code + ' — ' + (BOX_NAMES[code] || code);
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
    var people = collectAttendees();
    var childCount = 0;
    people.forEach(function (a) { if (a.child) childCount++; });
    var countLabel = String(people.length);
    if (childCount) countLabel += ' (' + childCount + ' under or 12 years old)';
    document.getElementById('sum-att-count').textContent = countLabel;
    var attendeeLines = people.map(function (a) {
      var line = (a.first + ' ' + a.last).trim() + ' — ' + lunchChoiceLabel(a.box);
      if (a.child) line += ' (under or 12 years old)';
      return line;
    });
    document.getElementById('sum-attendee-names').textContent = attendeeLines.join('; ');
    document.getElementById('sum-attendee-names-row').classList.toggle('hidden', !attendeeLines.length);

    var agg = aggregateBoxCounts();
    var rows = '', total = 0;
    Object.keys(agg).sort().forEach(function (code) {
      var qty = agg[code];
      var sub = (PRICES[code] || 0) * qty;
      total += sub;
      rows += '<tr>'
        + '<td class="py-1 pr-3"><span class="font-semibold text-indigo-700">' + esc(code) + '</span> ' + dishNameHtml(BOX_NAMES[code] || '') + '</td>'
        + '<td class="py-1 px-2 text-center whitespace-nowrap">' + qty + ' &times; $' + ((PRICES[code] || 0) / 100).toFixed(2) + '</td>'
        + '<td class="py-1 pl-3 text-right font-medium">$' + (sub / 100).toFixed(2) + '</td>'
        + '</tr>';
    });
    if (!rows) {
      rows = '<tr><td class="py-1 text-gray-500" colspan="3">No lunch boxes — attendance only</td></tr>';
    }
    document.getElementById('sum-rows').innerHTML = rows;
    document.getElementById('sum-total').textContent = '$' + (total / 100).toFixed(2);

    var confirmGo = document.getElementById('confirm-go');
    var confirmNote = document.getElementById('confirm-pay-note');
    if (total === 0) {
      confirmGo.textContent = 'Confirm RSVP';
      if (confirmNote) confirmNote.textContent = 'Your attendance will be recorded. No payment is required.';
    } else {
      confirmGo.textContent = 'Confirm & Pay with card';
      if (confirmNote) {
        confirmNote.textContent = checkoutModeElements
          ? 'Next you\'ll continue to our payment page to pay this amount by card. Your order is confirmed only after payment succeeds.'
          : 'Next you\'ll be taken to Stripe to pay this amount by card. Your order is confirmed only after payment succeeds.';
      }
    }
  }

  function openModal() { buildSummary(); modal.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
  function closeModal() { modal.classList.add('hidden'); document.body.style.overflow = ''; }

  form.addEventListener('submit', function (e) {
    if (!checkoutOk) {
      e.preventDefault();
      var banner = document.getElementById('ordering-window-banner');
      if (banner) banner.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
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
      // On-site /pay already shows the order summary — skip this review modal for paid
      // Elements checkouts. Keep it for free RSVP and for hosted Stripe (kill switch).
      var agg = aggregateBoxCounts();
      var paidTotal = 0;
      Object.keys(agg).forEach(function (code) {
        paidTotal += (PRICES[code] || 0) * agg[code];
      });
      if (checkoutModeElements && paidTotal > 0) {
        return;
      }
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

  var LUNCH_SEL = 'border-indigo-600 bg-indigo-50 text-indigo-800 ring-2 ring-indigo-300'.split(' ');
  var LUNCH_UNSEL = 'border-amber-200 bg-amber-50/60 text-gray-600 hover:border-amber-300'.split(' ');

  function fieldVal(n) {
    var el = form.querySelector('[name="' + n + '"]');
    return el ? el.value.trim() : '';
  }
  var MAX_ATTENDEES = 50;
  var attendeeList = document.getElementById('attendees');
  var addAttendeeBtn = document.getElementById('add-attendee');

  function setAttendCount(name, n) {
    var el = form.querySelector('input[data-attend="' + name + '"]');
    if (el) el.value = String(n);
  }
  function collectAttendees() {
    if (!attendeeList) return [];
    var out = [];
    attendeeList.querySelectorAll('[data-attendee-row]').forEach(function (row) {
      var first = row.querySelector('input[data-attendee-first]');
      var last = row.querySelector('input[data-attendee-last]');
      var child = row.querySelector('input[data-attendee-child]');
      var boxEl = row.querySelector('input[data-attendee-box]:checked');
      var editor = row.querySelector('[data-attendee-editor]');
      var complete = !!(first && first.value.trim() && last && last.value.trim() && boxEl && boxEl.value);
      var oked = !!(editor && editor.classList.contains('hidden') && complete);
      out.push({
        first: first ? first.value.trim() : '',
        last: last ? last.value.trim() : '',
        box: boxEl ? boxEl.value : '',
        child: !!(child && child.checked),
        oked: oked
      });
    });
    return out;
  }
  function attendanceComplete() {
    var rows = collectAttendees();
    if (rows.length < 1) return false;
    var adults = 0;
    var okedCount = 0;
    for (var i = 0; i < rows.length; i++) {
      // Every row must be OK'd (collapsed). Incomplete / in-edit rows block Continue.
      if (!rows[i].oked) return false;
      if (!rows[i].child) adults++;
      okedCount++;
    }
    return okedCount >= 1 && adults >= 1;
  }
  function aggregateBoxCounts() {
    var agg = {};
    collectAttendees().forEach(function (a) {
      if (a.box && a.box !== 'none') {
        agg[a.box] = (agg[a.box] || 0) + 1;
      }
    });
    return agg;
  }
  function syncLunchHighlights(row) {
    row.querySelectorAll('.lunch-option').forEach(function (opt) {
      var radio = opt.querySelector('input[data-attendee-box]');
      var on = !!(radio && radio.checked && !radio.disabled);
      LUNCH_SEL.forEach(function (c) { opt.classList.toggle(c, on); });
      LUNCH_UNSEL.forEach(function (c) { opt.classList.toggle(c, !on); });
    });
  }
  function selectLunchOption(row, radio) {
    if (!row || !radio || radio.disabled) return;
    row.querySelectorAll('input[data-attendee-box]').forEach(function (r) {
      r.checked = (r === radio);
    });
    syncLunchHighlights(row);
    syncAttendeeOkBtn(row);
    recalc();
  }
  function wireLunchGrid(row) {
    row.querySelectorAll('.lunch-option').forEach(function (opt) {
      var radio = opt.querySelector('input[data-attendee-box]');
      if (!radio) return;
      opt.addEventListener('click', function (e) {
        e.preventDefault();
        selectLunchOption(row, radio);
      });
    });
    syncLunchHighlights(row);
  }
  function lunchRemText(code, soldOut, rem) {
    if (soldOut) return 'Sold out';
    if (rem !== null && rem !== undefined && rem <= LOW_STOCK) return rem + ' left';
    return '';
  }
  function notOrderingLabel(groupName, selectedBox) {
    var on = selectedBox === 'none';
    return '<label class="lunch-option relative flex flex-col cursor-pointer items-center justify-center rounded-lg border-2 px-2 py-2 text-center text-md font-medium transition '
      + (on ? LUNCH_SEL.join(' ') : LUNCH_UNSEL.join(' ')) + '">'
      + '<input type="radio" name="' + groupName + '" value="none" class="sr-only" data-attendee-box data-box-code="none"'
      + (on ? ' checked' : '') + '>'
      + '<svg class="h-5 w-5 text-red-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">'
      + '<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>'
      + '</svg>'
      + '<span class="text-md leading-tight mt-0.5">留位但不點餐</span>'
      + '<span class="text-[10px] mt-0.5 min-h-[1em]"></span>'
      + '</label>';
  }
  function buildLunchGrid(index, selectedBox) {
    // Use attendee_box_0 style names (no brackets) so radios group reliably in all browsers.
    var groupName = 'attendee_box_' + index;
    var html = '<div class="grid grid-cols-2 gap-2 mt-2" data-lunch-grid="attendee-' + index + '">';
    BOXES.forEach(function (b, i) {
      var soldOut = !!(b.sold_out || (remainingInfo[b.code] && remainingInfo[b.code].sold_out));
      var rem = remainingInfo[b.code] ? remainingInfo[b.code].remaining : null;
      var remText = lunchRemText(b.code, soldOut, rem);
      var wide = (b.name || '').length > 16;
      var on = selectedBox === b.code && !soldOut;
      html += '<label class="lunch-option relative flex flex-col cursor-pointer items-center justify-center rounded-lg border-2 px-2 py-2 text-center text-md font-medium transition '
        + (wide ? 'col-span-2 ' : '')
        + (soldOut ? 'opacity-60 cursor-not-allowed ' : '')
        + (on ? LUNCH_SEL.join(' ') : LUNCH_UNSEL.join(' ')) + '" data-box-code="' + esc(b.code) + '">'
        + '<input type="radio" name="' + groupName + '" value="' + esc(b.code) + '" class="sr-only" data-attendee-box data-box-code="' + esc(b.code) + '"'
        + (on ? ' checked' : '')
        + (soldOut ? ' disabled' : '') + '>'
        + '<span class="font-bold">' + esc(b.code) + '</span>'
        + '<span class="leading-tight">' + dishNameHtml(b.name) + '</span>'
        + '<span class="text-[10px] mt-0.5 min-h-[1em] ' + (soldOut ? 'text-red-600 font-semibold' : 'text-amber-600') + '" data-lunch-rem="' + esc(b.code) + '">' + esc(remText) + '</span>'
        + '</label>';
      if (i === 4) html += notOrderingLabel(groupName, selectedBox);
    });
    if (BOXES.length < 5) html += notOrderingLabel(groupName, selectedBox);
    html += '</div>';
    return html;
  }
  function attendeeRowData(row) {
    var first = row.querySelector('input[data-attendee-first]');
    var last = row.querySelector('input[data-attendee-last]');
    var child = row.querySelector('input[data-attendee-child]');
    var boxEl = row.querySelector('input[data-attendee-box]:checked');
    return {
      first: first ? first.value.trim() : '',
      last: last ? last.value.trim() : '',
      child: !!(child && child.checked),
      box: boxEl ? boxEl.value : ''
    };
  }
  function rowComplete(row) {
    var d = attendeeRowData(row);
    return !!(d.first && d.last && d.box);
  }
  function applyAttendeeData(row, data) {
    if (!row || !data) return;
    var first = row.querySelector('input[data-attendee-first]');
    var last = row.querySelector('input[data-attendee-last]');
    var child = row.querySelector('input[data-attendee-child]');
    if (first) first.value = data.first || '';
    if (last) last.value = data.last || '';
    if (child) child.checked = !!data.child;
    row.querySelectorAll('input[data-attendee-box]').forEach(function (r) {
      r.checked = r.value === (data.box || '');
    });
    syncLunchHighlights(row);
  }
  function syncAttendeeOkBtn(row) {
    var ok = row && row.querySelector('[data-attendee-ok]');
    if (!ok) return;
    ok.disabled = !rowComplete(row);
    var snap = row._editSnapshot;
    var editingSaved = !!(snap && snap.first && snap.last && snap.box);
    ok.textContent = editingSaved ? '更改' : '加入帳單';
  }
  function syncAddNextBtn() {
    if (!addAttendeeBtn || !attendeeList) return;
    var people = collectAttendees();
    var editing = !!attendeeList.querySelector('[data-attendee-editor]:not(.hidden)');
    var hasOked = people.some(function (p) { return p.oked; });
    addAttendeeBtn.classList.toggle('hidden', editing || !hasOked);
    addAttendeeBtn.disabled = people.length >= MAX_ATTENDEES;
  }
  function setRowMode(row, collapsed) {
    var editor = row.querySelector('[data-attendee-editor]');
    var summary = row.querySelector('[data-attendee-summary]');
    if (!editor || !summary) return;
    if (collapsed && !rowComplete(row)) collapsed = false;
    editor.classList.toggle('hidden', collapsed);
    summary.classList.toggle('hidden', !collapsed);
    summary.classList.toggle('flex', collapsed);
    row.classList.toggle('p-3', !collapsed);
    row.classList.toggle('space-y-2', !collapsed);
    row.classList.toggle('py-2', collapsed);
    row.classList.toggle('px-3', collapsed);
    if (collapsed) {
      var d = attendeeRowData(row);
      var n = (parseInt(row.getAttribute('data-attendee-row'), 10) || 0) + 1;
      var lunchName = d.box === 'none' ? 'Not Ordering' : (BOX_NAMES[d.box] || d.box);
      var lunchCode = (d.box && d.box !== 'none') ? ' (' + esc(d.box) + ')' : '';
      var el = summary.querySelector('[data-attendee-summary-text]');
      if (el) {
        el.innerHTML = '<span class="block font-semibold text-base leading-tight">' + n + '. ' + esc((d.first + ' ' + d.last).trim()) + '</span>'
          + '<span class="block text-sm text-gray-700 leading-snug">' + dishNameHtml(lunchName) + lunchCode + ' ' + (d.child ? '孩童' : '成人') + '</span>';
      }
      row._editSnapshot = null;
    } else {
      row._editSnapshot = attendeeRowData(row);
      syncAttendeeOkBtn(row);
    }
    syncAddNextBtn();
  }
  function renderAttendees(rows, focusIndex) {
    if (!attendeeList) return;
    attendeeList.innerHTML = '';
    rows.forEach(function (data, i) {
      var row = document.createElement('div');
      row.className = 'rounded-lg border border-gray-200 p-3 space-y-2';
      row.setAttribute('data-attendee-row', String(i));
      if (i === 0) {
        var regFirst = fieldVal('first_name');
        var regLast = fieldVal('last_name');
        var prevFirst = attendeeList._lastRegFirst || '';
        var prevLast = attendeeList._lastRegLast || '';
        if (!String(data.first || '').trim() || data.first === prevFirst) data.first = regFirst;
        if (!String(data.last || '').trim() || data.last === prevLast) data.last = regLast;
        attendeeList._lastRegFirst = regFirst;
        attendeeList._lastRegLast = regLast;
      }
      var removeBtn = rows.length > 1
        ? '<button type="button" data-remove-attendee aria-label="Remove" class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-red-600 hover:bg-red-50 hover:text-red-800">'
          + '<svg class="h-6 w-6" viewBox="0 0 20 20" fill="none" aria-hidden="true">'
          + '<circle cx="10" cy="10" r="8.25" stroke="currentColor" stroke-width="1.5"/>'
          + '<path d="M7.2 7.2l5.6 5.6M12.8 7.2l-5.6 5.6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>'
          + '</svg></button>'
        : '';
      row.innerHTML =
        '<div data-attendee-summary class="hidden items-center justify-between gap-2">'
        + '<p class="min-w-0 break-words text-sm text-gray-900" data-attendee-summary-text></p>'
        + '<span class="flex shrink-0 items-center gap-3">'
        + '<button type="button" data-attendee-edit class="text-sm font-semibold text-indigo-700 hover:text-indigo-900">更改</button>'
        + removeBtn
        + '</span></div>'
        + '<div data-attendee-editor class="space-y-2">'
        + '<div class="flex items-center justify-between gap-2">'
        + '<p class="text-xl font-semibold text-gray-800"> 第' + (i + 1) + '位出席者 '+'</p>'
        + removeBtn
        + '</div>'
        + '<div class="grid sm:grid-cols-2 gap-3">'
        + '<label class="block"><span class="text-lg font-medium text-gray-600">First name <span class="text-red-600">*</span></span>'
        + '<input type="text" name="attendee_first[' + i + ']" data-attendee-first required maxlength="100" autocomplete="given-name"'
        + ' class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" value="' + esc(data.first || '') + '"></label>'
        + '<label class="block"><span class="text-lg font-medium text-gray-600">Last name <span class="text-red-600">*</span></span>'
        + '<input type="text" name="attendee_last[' + i + ']" data-attendee-last required maxlength="100" autocomplete="family-name"'
        + ' class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" value="' + esc(data.last || '') + '"></label>'
        + '</div>'
        + '<label class="flex items-center gap-2 cursor-pointer select-none text-sm text-gray-800">'
        + '<input type="checkbox" name="attendee_child_' + i + '" value="1" data-attendee-child'
        + ' class="text-md h-5 w-5 rounded border-gray-400 text-indigo-600 focus:ring-indigo-500"'
        + (data.child ? ' checked' : '') + '>'
        + '<span class="text-lg">12歲或以下</span></label>'
        + '<p class="text-lg font-medium text-gray-600">請點餐 <span class="text-red-600">*</span></p>'
        + buildLunchGrid(i, data.box || '')
        + '<div class="flex flex-wrap items-center gap-3 pt-1">'
        + '<button type="button" data-attendee-ok class="btn-primary px-5 py-2 text-sm" disabled>OK</button>'
        + '<button type="button" data-attendee-cancel class="rounded-md border-2 border-gray-300 bg-white px-5 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</button>'
        + '</div>'
        + '</div>';
      attendeeList.appendChild(row);
      wireLunchGrid(row);
      row.querySelectorAll('input[data-attendee-first], input[data-attendee-last]').forEach(function (input) {
        input.addEventListener('input', function () { syncAttendeeOkBtn(row); });
      });
      var childBox = row.querySelector('input[data-attendee-child]');
      if (childBox) {
        childBox.addEventListener('change', function () { syncAttendeeOkBtn(row); });
      }
      var okBtn = row.querySelector('[data-attendee-ok]');
      if (okBtn) {
        okBtn.addEventListener('click', function () {
          if (!rowComplete(row)) {
            syncAttendeeOkBtn(row);
            return;
          }
          setRowMode(row, true);
          recalc();
        });
      }
      var cancelBtn = row.querySelector('[data-attendee-cancel]');
      if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
          var snap = row._editSnapshot;
          var idx = parseInt(row.getAttribute('data-attendee-row'), 10) || 0;
          if (snap && snap.first && snap.last && snap.box) {
            applyAttendeeData(row, snap);
            setRowMode(row, true);
            recalc();
            return;
          }
          var next = collectAttendees();
          if (next.length > 1) {
            next.splice(idx, 1);
            renderAttendees(next);
            recalc();
            return;
          }
          applyAttendeeData(row, {
            first: fieldVal('first_name'),
            last: fieldVal('last_name'),
            box: '',
            child: false
          });
          syncAttendeeOkBtn(row);
          recalc();
        });
      }
      var editBtn = row.querySelector('[data-attendee-edit]');
      if (editBtn) {
        editBtn.addEventListener('click', function () {
          setRowMode(row, false);
          recalc();
          var firstEl = row.querySelector('input[data-attendee-first]');
          if (firstEl) firstEl.focus();
        });
      }
      // Preserve OK'd (collapsed) state across re-renders; never auto-OK without a click.
      if (data.oked && rowComplete(row) && focusIndex !== i) {
        setRowMode(row, true);
      } else {
        // Edit mode: previously OK'd → Save/Cancel restore; brand-new → OK label.
        row._editSnapshot = (data.oked && rowComplete(row))
          ? attendeeRowData(row)
          : { first: '', last: '', box: '', child: false };
        syncAttendeeOkBtn(row);
      }
      if (i === 0) {
        row.querySelector('input[data-attendee-first]').addEventListener('input', function () {
          attendeeList._adult1Edited = true;
        });
        row.querySelector('input[data-attendee-last]').addEventListener('input', function () {
          attendeeList._adult1Edited = true;
        });
      }
      row.querySelectorAll('[data-remove-attendee]').forEach(function (remove) {
        remove.addEventListener('click', function () {
          var next = collectAttendees();
          if (next.length <= 1) return;
          next.splice(i, 1);
          renderAttendees(next);
          recalc();
        });
      });
    });
    if (addAttendeeBtn) addAttendeeBtn.disabled = rows.length >= MAX_ATTENDEES;
    syncAddNextBtn();
    if (focusIndex != null) {
      var focusRow = attendeeList.querySelector('[data-attendee-row="' + focusIndex + '"]');
      var focusEl = focusRow && focusRow.querySelector('input[data-attendee-first]');
      if (focusEl) focusEl.focus();
    }
  }
  function addAttendee() {
    var rows = collectAttendees();
    if (!rows.length) {
      rows = [{ first: fieldVal('first_name'), last: fieldVal('last_name'), box: '', child: false }];
    }
    if (rows.length >= MAX_ATTENDEES) return;
    rows.push({ first: '', last: '', box: '', child: false });
    renderAttendees(rows, rows.length - 1);
    recalc();
  }
  function syncAdult1FromDetails() {
    if (!attendeeList || attendeeList._adult1Edited) return;
    var row = attendeeList.querySelector('[data-attendee-row="0"]');
    if (!row) return;
    var firstEl = row.querySelector('input[data-attendee-first]');
    var lastEl = row.querySelector('input[data-attendee-last]');
    if (!firstEl || !lastEl) return;
    var regFirst = fieldVal('first_name');
    var regLast = fieldVal('last_name');
    var prevFirst = attendeeList._lastRegFirst || '';
    var prevLast = attendeeList._lastRegLast || '';
    if (!firstEl.value.trim() || firstEl.value === prevFirst) firstEl.value = regFirst;
    if (!lastEl.value.trim() || lastEl.value === prevLast) lastEl.value = regLast;
    attendeeList._lastRegFirst = regFirst;
    attendeeList._lastRegLast = regLast;
    var summary = row.querySelector('[data-attendee-summary]');
    if (summary && !summary.classList.contains('hidden')) setRowMode(row, true);
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
  function campusComplete() {
    return CAMPUSES_OK && !!campusChosen() && fieldVal('lift_group') !== '';
  }
  function formIsComplete() {
    return detailsComplete() && attendanceComplete() && campusComplete();
  }

  function setStepOpen(stepEl, open) {
    if (!stepEl) return;
    stepEl.classList.toggle('form-step-locked', !open);
    stepEl.setAttribute('aria-disabled', open ? 'false' : 'true');
    if ('inert' in stepEl) stepEl.inert = !open;
  }

  // When Your details first becomes complete, start with the registrant as attendance 1.
  var detailsWasComplete = false;
  function ensureRegistrantAsFirstAttendee() {
    if (collectAttendees().length < 1) {
      renderAttendees([{
        first: fieldVal('first_name'),
        last: fieldVal('last_name'),
        box: '',
        child: false
      }]);
    } else {
      syncAdult1FromDetails();
    }
  }
  function syncFormSteps() {
    var dOk = detailsComplete();
    if (dOk && !detailsWasComplete) {
      detailsWasComplete = true;
      ensureRegistrantAsFirstAttendee();
    } else if (!dOk) {
      detailsWasComplete = false;
    }
    dOk = detailsComplete();
    var cOk = dOk && campusComplete();
    // After the deadline (or admin closed), keep steps greyed. Before opening day,
    // allow filling the form; Continue stays locked via checkoutOk.
    setStepOpen(form.querySelector('[data-form-step="details"]'), formBrowseOk);
    if (progressiveSteps) {
      setStepOpen(form.querySelector('[data-form-step="campus"]'), formBrowseOk && dOk);
      setStepOpen(form.querySelector('[data-form-step="attendance"]'), formBrowseOk && cOk);
      setStepOpen(form.querySelector('[data-form-step="checkout"]'), formBrowseOk && cOk);
    } else {
      setStepOpen(form.querySelector('[data-form-step="campus"]'), formBrowseOk);
      setStepOpen(form.querySelector('[data-form-step="attendance"]'), formBrowseOk);
      setStepOpen(form.querySelector('[data-form-step="checkout"]'), formBrowseOk);
    }
  }

  (function seedAttendees() {
    if (!attendeeList) return;
    var initial = [];
    try { initial = JSON.parse(attendeeList.dataset.initialAttendees || '[]') || []; } catch (e) { initial = []; }
    attendeeList.dataset.initialAttendees = '[]';
    if (initial.length) renderAttendees(initial);
  })();
  if (addAttendeeBtn) addAttendeeBtn.addEventListener('click', addAttendee);

  function syncAttendCounts() {
    var people = collectAttendees();
    var children = 0;
    people.forEach(function (a) { if (a.child) children++; });
    setAttendCount('attending_adults', people.length - children);
    setAttendCount('attending_children', children);
    var err = document.getElementById('attendance-adult-error');
    if (err) err.classList.toggle('hidden', people.length < 1 || children < people.length);
    syncAddNextBtn();
  }

  function recalc() {
    syncFormSteps();
    syncAttendCounts();
    var agg = aggregateBoxCounts();
    var cents = 0;
    Object.keys(agg).forEach(function (code) {
      cents += (PRICES[code] || 0) * agg[code];
    });
    totalEl.textContent = '$' + (cents / 100).toFixed(2);
    syncFormSteps();
    payBtn.disabled = !checkoutOk || !formIsComplete();
    payBtn.textContent = cents === 0 ? 'Continue' : 'Continue';
    if (!checkoutOk) {
      payBtn.title = formBrowseOk
        ? 'Ordering has not opened yet — you can fill the form, but checkout is not available'
        : 'Checkout is only available during the ordering window';
    } else if (!CAMPUSES_OK) {
      payBtn.title = 'Campus options are not configured';
    } else {
      payBtn.title = '';
    }
  }

  function applyRemaining(data) {
    if (!data || !data.ok) return;
    if (typeof data.checkout_open === 'boolean') {
      checkoutOk = data.checkout_open;
      if (payBtn) payBtn.setAttribute('data-checkout-ok', checkoutOk ? '1' : '0');
    }
    remainingInfo = data.boxes || {};
    // Update sold-out state in place — do not rebuild rows (that resets selection/highlight).
    form.querySelectorAll('[data-attendee-row]').forEach(function (row) {
      BOXES.forEach(function (b) {
        var info = remainingInfo[b.code] || {};
        var soldOut = !!info.sold_out;
        var rem = typeof info.remaining === 'number' ? info.remaining : null;
        var remEl = row.querySelector('[data-lunch-rem="' + b.code + '"]');
        var radio = row.querySelector('input[data-attendee-box][data-box-code="' + b.code + '"]');
        var opt = radio ? radio.closest('.lunch-option') : null;
        if (remEl) {
          remEl.textContent = lunchRemText(b.code, soldOut, rem);
          remEl.className = 'text-[10px] mt-0.5 min-h-[1em] ' + (soldOut ? 'text-red-600 font-semibold' : 'text-amber-600');
        }
        if (radio) {
          radio.disabled = soldOut;
          if (soldOut && radio.checked) {
            radio.checked = false;
            var noneRadio = row.querySelector('input[data-attendee-box][data-box-code="none"]');
            if (noneRadio) noneRadio.checked = true;
          }
        }
        if (opt) {
          opt.classList.toggle('opacity-60', soldOut);
          opt.classList.toggle('cursor-not-allowed', soldOut);
        }
      });
      syncLunchHighlights(row);
      var summary = row.querySelector('[data-attendee-summary]');
      if (summary && !summary.classList.contains('hidden')) setRowMode(row, true);
    });
    recalc();
  }

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

<?php layout_footer(); ?>
