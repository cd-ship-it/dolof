<?php
/**
 * Lunch-box catalogue + capacity accounting.
 *
 * A box "set" has a hard cap. Capacity is consumed by:
 *   - paid orders, and
 *   - pending orders whose hold has not expired.
 * See dolof_capacity_sql() for the single source of truth.
 */
require_once __DIR__ . '/logger.php';

const DOLOS_TBL_BOXES        = 'dolos_boxes';
const DOLOS_TBL_ORDERS       = 'dolos_orders';
const DOLOS_TBL_ITEMS        = 'dolos_order_items';
const DOLOS_TBL_SETTINGS     = 'dolos_settings';
const DOLOS_TBL_STAFF_TOKENS = 'dolos_staff_tokens';

class BoxCapacityException extends RuntimeException
{
    /** @param string[] $errors human-readable, $soldOutCodes machine list */
    public function __construct(private array $errors, private array $soldOutCodes = [])
    {
        parent::__construct($errors[0] ?? 'One or more lunch boxes are sold out.');
    }

    /** @return string[] */
    public function getErrors(): array { return $this->errors; }

    /** @return string[] */
    public function getSoldOutCodes(): array { return $this->soldOutCodes; }
}

function box_lock_name(string $code): string
{
    return 'dolos_box:' . strtoupper($code);
}

// ─── Settings ────────────────────────────────────────────────────────────────

function dolos_setting(PDO $pdo, string $key, ?string $default = null): ?string
{
    $stmt = $pdo->prepare('SELECT `value` FROM ' . DOLOS_TBL_SETTINGS . ' WHERE `key` = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['value'] : $default;
}

function dolos_setting_set(PDO $pdo, string $key, string $value): void
{
    $pdo->prepare(
        'INSERT INTO ' . DOLOS_TBL_SETTINGS . ' (`key`, `value`) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
    )->execute([$key, $value]);
}

function ordering_is_open(PDO $pdo): bool
{
    return dolos_setting($pdo, 'ordering_open', '1') === '1';
}

/**
 * Site / event title for headers, <title>, and emails.
 * Uses admin-edited event_title when present; otherwise APP_TITLE from config.
 */
function app_title(?PDO $pdo = null): string
{
    $pdo = $pdo ?? ($GLOBALS['pdo'] ?? null);
    if ($pdo instanceof PDO) {
        $t = trim((string) dolos_setting($pdo, 'event_title', ''));
        if ($t !== '') {
            return $t;
        }
    }
    return defined('APP_TITLE') ? APP_TITLE : '執事按立禮感恩午宴';
}

/** Parse ORDERING_START / ORDERING_END from .env (Pacific Time). */
function ordering_env_time(string $constantOrRaw): ?DateTimeImmutable
{
    $raw = trim($constantOrRaw);
    if ($raw === '') {
        return null;
    }
    try {
        return new DateTimeImmutable($raw);
    } catch (Exception $e) {
        return null;
    }
}

function ordering_window_start(): ?DateTimeImmutable
{
    return ordering_env_time(defined('ORDERING_START') ? ORDERING_START : (string) env('ORDERING_START', ''));
}

function ordering_window_end(): ?DateTimeImmutable
{
    return ordering_env_time(defined('ORDERING_END') ? ORDERING_END : (string) env('ORDERING_END', ''));
}

/** True when now is within [ORDERING_START, ORDERING_END] (inclusive). Missing bounds are open-ended. */
function ordering_within_window(?DateTimeInterface $now = null): bool
{
    $now = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)
        : new DateTimeImmutable('now');
    $start = ordering_window_start();
    $end   = ordering_window_end();
    if ($start && $now < $start) {
        return false;
    }
    if ($end && $now > $end) {
        return false;
    }
    return true;
}

/** Admin toggle open AND within the .env schedule — required to submit checkout. */
function ordering_accepts_checkout(PDO $pdo): bool
{
    return ordering_is_open($pdo) && ordering_within_window();
}

/**
 * Payment UI mode: "elements" (on-site Payment Element) or "hosted" (Stripe Checkout page).
 * Admin kill switch; default elements while trying the custom pay page.
 */
function checkout_mode(PDO $pdo): string
{
    $mode = strtolower(trim((string) dolos_setting($pdo, 'checkout_mode', 'elements')));
    return $mode === 'hosted' ? 'hosted' : 'elements';
}

function checkout_mode_is_elements(PDO $pdo): bool
{
    return checkout_mode($pdo) === 'elements';
}

/** Human-readable Pacific time for banners (e.g. "Sep 20, 2026 at 2:00 PM PT"). */
function ordering_format_pt(?DateTimeImmutable $dt): string
{
    if (!$dt) {
        return '';
    }
    return $dt->format('M j, Y') . ' at ' . ltrim($dt->format('g:i A'), '0') . ' PT';
}

// ─── Box catalogue ───────────────────────────────────────────────────────────

/** @return array<int,array> active boxes ordered for display */
function get_active_boxes(PDO $pdo): array
{
    return $pdo->query(
        'SELECT * FROM ' . DOLOS_TBL_BOXES . ' WHERE active = 1 ORDER BY sort_order, code'
    )->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<int,array> every box, active or not */
function get_all_boxes(PDO $pdo): array
{
    return $pdo->query(
        'SELECT * FROM ' . DOLOS_TBL_BOXES . ' ORDER BY sort_order, code'
    )->fetchAll(PDO::FETCH_ASSOC);
}

function get_box(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM ' . DOLOS_TBL_BOXES . ' WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// ─── Capacity ────────────────────────────────────────────────────────────────

/**
 * Quantities that currently consume capacity, keyed by box_id.
 *
 * @return array<int,int> box_id => taken
 */
function box_taken_counts(PDO $pdo, ?int $excludeOrderId = null): array
{
    $sql = 'SELECT oi.box_id, COALESCE(SUM(oi.quantity), 0) AS taken
              FROM ' . DOLOS_TBL_ITEMS . ' oi
              JOIN ' . DOLOS_TBL_ORDERS . " o ON o.id = oi.order_id
             WHERE (o.status = 'paid'
                    OR (o.status = 'pending'
                        AND o.hold_expires_at IS NOT NULL
                        AND o.hold_expires_at > NOW()))";
    $params = [];
    if ($excludeOrderId !== null && $excludeOrderId > 0) {
        $sql .= ' AND o.id <> ?';
        $params[] = $excludeOrderId;
    }
    $sql .= ' GROUP BY oi.box_id';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[(int) $row['box_id']] = (int) $row['taken'];
    }
    return $out;
}

/**
 * Active boxes decorated with taken/remaining/sold_out.
 *
 * @return array<int,array>
 */
function boxes_with_remaining(PDO $pdo, ?int $excludeOrderId = null): array
{
    $taken = box_taken_counts($pdo, $excludeOrderId);
    $boxes = get_active_boxes($pdo);
    foreach ($boxes as &$b) {
        $t = $taken[(int) $b['id']] ?? 0;
        $b['taken']     = $t;
        $b['remaining'] = max(0, (int) $b['cap'] - $t);
        $b['sold_out']  = $b['remaining'] <= 0;
    }
    unset($b);
    return $boxes;
}

/** Paid-only quantities per box_id (for the admin dashboard). */
function box_paid_counts(PDO $pdo): array
{
    $rows = $pdo->query(
        'SELECT oi.box_id, COALESCE(SUM(oi.quantity),0) AS qty
           FROM ' . DOLOS_TBL_ITEMS . ' oi
           JOIN ' . DOLOS_TBL_ORDERS . " o ON o.id = oi.order_id
          WHERE o.status = 'paid'
          GROUP BY oi.box_id"
    )->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) {
        $out[(int) $r['box_id']] = (int) $r['qty'];
    }
    return $out;
}

/** Pending (held, not yet expired) quantities per box_id. */
function box_held_counts(PDO $pdo): array
{
    $rows = $pdo->query(
        'SELECT oi.box_id, COALESCE(SUM(oi.quantity),0) AS qty
           FROM ' . DOLOS_TBL_ITEMS . ' oi
           JOIN ' . DOLOS_TBL_ORDERS . " o ON o.id = oi.order_id
          WHERE o.status = 'pending'
            AND o.hold_expires_at IS NOT NULL
            AND o.hold_expires_at > NOW()
          GROUP BY oi.box_id"
    )->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) {
        $out[(int) $r['box_id']] = (int) $r['qty'];
    }
    return $out;
}
