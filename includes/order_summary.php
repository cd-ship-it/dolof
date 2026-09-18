<?php
/**
 * Shared order summary for /pay and /success (and anywhere else).
 */

/**
 * Per-person lunch rows for an order.
 *
 * @return list<array{name:string,box_html:string,subtotal:int}>
 */
function order_lunch_rows(array $order): array
{
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

    $rows = [];
    $adults = decode_attendees($order['adult_names'] ?? null);
    $children = decode_attendees($order['child_names'] ?? null);
    foreach (array_merge($adults, $children) as $a) {
        $name = attendee_full_name($a);
        $code = trim((string) ($a['box'] ?? ''));
        if ($name === '') {
            continue;
        }
        if ($code === '' || $code === 'none') {
            $rows[] = [
                'name'     => $name,
                'box_html' => e('留位但不點餐'),
                'subtotal' => 0,
            ];
            continue;
        }
        $info = $boxByCode[$code] ?? ['name' => $code, 'price' => 0];
        $rows[] = [
            'name'     => $name,
            'box_html' => '<span class="font-semibold text-indigo-700">' . e($code) . '</span> '
                . dish_name_html($info['name']),
            'subtotal' => (int) $info['price'],
        ];
    }
    return $rows;
}

/**
 * Per-person attendance lines for list/export views.
 * Adults first, then children (marked).
 *
 * @return list<string>
 */
function order_attendance_lines(array $order): array
{
    $lines = [];
    foreach (decode_attendees($order['adult_names'] ?? null) as $a) {
        $line = format_attendee_line($a);
        if ($line !== '') {
            $lines[] = $line;
        }
    }
    foreach (decode_attendees($order['child_names'] ?? null) as $a) {
        $line = format_attendee_line($a);
        if ($line !== '') {
            $lines[] = $line . ' · 孩童';
        }
    }
    return $lines;
}

function order_attendance_summary(array $order, string $sep = '; '): string
{
    return implode($sep, order_attendance_lines($order));
}

function order_attendance_count(array $order): int
{
    return count(decode_attendees($order['adult_names'] ?? null))
        + count(decode_attendees($order['child_names'] ?? null));
}

/**
 * Render the order summary card (contact + per-person lunch table).
 *
 * Options:
 *   heading      (string)  default 訂單摘要
 *   total_label  (string)  footer label, e.g. 應付總額 / 已付總額
 *   show_order_id (bool)   append #id to heading
 *   card_class   (string)  extra classes on the outer card
 */
function render_order_summary(array $order, array $opts = []): void
{
    $heading    = (string) ($opts['heading'] ?? '訂單摘要');
    $totalLabel = (string) ($opts['total_label'] ?? '應付總額');
    $cardClass  = trim('card mb-6 space-y-3 text-sm ' . (string) ($opts['card_class'] ?? ''));
    $showId     = !empty($opts['show_order_id']);
    $orderId    = (int) ($order['id'] ?? 0);
    if ($showId && $orderId > 0) {
        $heading .= ' #' . $orderId;
    }

    $customerName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
    $phone = trim((string) ($order['phone'] ?? ''));
    $email = trim((string) ($order['email'] ?? ''));
    $lunchRows = order_lunch_rows($order);
    $totalCents = (int) ($order['total_amount_cents'] ?? 0);
    ?>
<div class="<?= e($cardClass) ?>">
  <h2 class="text-base font-semibold text-gray-900"><?= e($heading) ?></h2>
  <div class="text-gray-800 space-y-0.5">
    <div>
      <span class="font-bold text-gray-900">姓名</span> <?= e($customerName) ?>
      <?php if ($phone !== ''): ?>
        <span class="text-gray-300 mx-1.5">·</span>
        <span class="font-bold text-gray-900">電話</span> <?= e($phone) ?>
      <?php endif; ?>
    </div>
    <?php if ($email !== ''): ?>
      <div><span class="text-gray-500">Email:</span> <?= e($email) ?></div>
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
        <td class="pt-3 font-bold text-base" colspan="2"><?= e($totalLabel) ?></td>
        <td class="pt-3 font-bold text-base text-right text-indigo-900"><?= e(money($totalCents)) ?></td>
      </tr>
    </tfoot>
  </table>
  <?php elseif ($totalCents === 0): ?>
  <p class="text-sm text-gray-600 border-t border-gray-200 pt-2">No lunch boxes ordered — attendance only.</p>
  <?php endif; ?>
</div>
    <?php
}
