<?php
/**
 * Small shared helpers.
 */

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function money(int $cents): string
{
    return '$' . number_format($cents / 100, 2);
}

/** App-relative asset URL with a cache-busting ?v=<mtime>. */
function asset_url(string $path): string
{
    $base = defined('APP_URL') ? APP_URL : '';
    $rel  = ltrim($path, '/');
    $file = dirname(__DIR__) . '/' . $rel;
    $ver  = is_file($file) ? filemtime($file) : time();
    return $base . '/' . $rel . '?v=' . $ver;
}

/**
 * Render a dish name as HTML with its English (Latin) runs a little smaller and
 * lighter, so they sit nicely beside Chinese text. Output is fully escaped.
 */
function dish_name_html(string $name): string
{
    $parts = preg_split('/([\x20-\x7E]+)/', $name, -1, PREG_SPLIT_DELIM_CAPTURE);
    $html  = '';
    foreach ($parts as $p) {
        if ($p === '') {
            continue;
        }
        if (preg_match('/[A-Za-z]/', $p) && !preg_match('/[^\x20-\x7E]/', $p)) {
            $lead  = $p[0] === ' ' ? ' ' : '';
            $trail = substr($p, -1) === ' ' ? ' ' : '';
            $html .= $lead . '<span class="text-[0.85em] font-normal text-gray-500">' . e(trim($p)) . '</span>' . $trail;
        } else {
            $html .= e($p);
        }
    }
    return $html;
}

/** Comma/newline separated list of valid emails from an .env value. */
function email_list(string $raw): array
{
    $parts = preg_split('/[,\n]/', $raw) ?: [];
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '' && filter_var($p, FILTER_VALIDATE_EMAIL)) {
            $out[] = $p;
        }
    }
    return array_values(array_unique($out));
}

/**
 * Single source of truth for campuses and life-group suggestions:
 * data/life-groups.json (object keys = campus names, values = suggestion lists).
 *
 * @return array<string, list<string>>
 */
function life_groups_by_campus(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $file = dirname(__DIR__) . '/data/life-groups.json';
    if (!is_file($file)) {
        return $cached = [];
    }

    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data)) {
        return $cached = [];
    }

    $out = [];
    foreach ($data as $campus => $groups) {
        $campus = trim((string) $campus);
        if ($campus === '') {
            continue;
        }
        $list = [];
        if (is_array($groups)) {
            foreach ($groups as $g) {
                $g = trim((string) $g);
                if ($g !== '') {
                    $list[] = $g;
                }
            }
        }
        $out[$campus] = $list;
    }
    return $cached = $out;
}

/** Campus names from life-groups.json, in file order. */
function campuses(): array
{
    return array_keys(life_groups_by_campus());
}

/**
 * Normalize a posted list of attendee names to exactly $requireCount entries.
 * When $requireCount is 0, returns [].
 *
 * @param mixed $raw
 * @return list<string>
 */
function normalize_attendee_names($raw, int $requireCount): array
{
    $list = [];
    if (is_array($raw)) {
        foreach ($raw as $name) {
            $list[] = trim((string) $name);
        }
    }
    if ($requireCount < 1) {
        return [];
    }
    $out = [];
    for ($i = 0; $i < $requireCount; $i++) {
        $out[] = $list[$i] ?? '';
    }
    return $out;
}

/** Encode attendee names for DB storage (JSON array). */
function encode_attendee_names(array $names): string
{
    return json_encode(array_values($names), JSON_UNESCAPED_UNICODE) ?: '[]';
}

/**
 * Normalize posted per-person attendee rows to exactly $requireCount entries.
 *
 * @param mixed $firstRaw
 * @param mixed $lastRaw
 * @param mixed $boxRaw
 * @return list<array{first:string,last:string,box:string}>
 */
function normalize_attendees($firstRaw, $lastRaw, $boxRaw, int $requireCount): array
{
    $firsts = is_array($firstRaw) ? array_map(fn($v) => trim((string) $v), $firstRaw) : [];
    $lasts  = is_array($lastRaw) ? array_map(fn($v) => trim((string) $v), $lastRaw) : [];
    $boxes  = is_array($boxRaw) ? array_map(fn($v) => trim((string) $v), $boxRaw) : [];
    if ($requireCount < 1) {
        return [];
    }
    $out = [];
    for ($i = 0; $i < $requireCount; $i++) {
        $out[] = [
            'first' => $firsts[$i] ?? '',
            'last'  => $lasts[$i] ?? '',
            'box'   => $boxes[$i] ?? '',
        ];
    }
    return $out;
}

/**
 * Read lunch choices posted as adult_box_0 / child_box_1 (radio-safe names).
 *
 * @return list<string>
 */
function posted_attendee_boxes(string $kind, int $count): array
{
    $out = [];
    for ($i = 0; $i < $count; $i++) {
        $out[] = trim((string) ($_POST[$kind . '_box_' . $i] ?? ''));
    }
    // Fallback: legacy adult_box[] / adult_box[0] shapes.
    if ($out === [] || ($count > 0 && $out[0] === '')) {
        $legacy = $_POST[$kind . '_box'] ?? null;
        if (is_array($legacy)) {
            for ($i = 0; $i < $count; $i++) {
                if (($out[$i] ?? '') === '') {
                    $out[$i] = trim((string) ($legacy[$i] ?? ''));
                }
            }
        }
    }
    return $out;
}

/**
 * Read unified attendance rows posted by the order form.
 * Checkbox attendee_child_N is present only when that person is 12 or under.
 *
 * @return list<array{first:string,last:string,box:string,child:bool}>
 */
function posted_form_attendees(int $max = 50): array
{
    $firsts = is_array($_POST['attendee_first'] ?? null) ? array_values($_POST['attendee_first']) : [];
    $lasts  = is_array($_POST['attendee_last'] ?? null) ? array_values($_POST['attendee_last']) : [];
    $count  = min($max, max(count($firsts), count($lasts)));
    $out = [];
    for ($i = 0; $i < $count; $i++) {
        $out[] = [
            'first' => trim((string) ($firsts[$i] ?? '')),
            'last'  => trim((string) ($lasts[$i] ?? '')),
            'box'   => trim((string) ($_POST['attendee_box_' . $i] ?? '')),
            'child' => isset($_POST['attendee_child_' . $i]),
        ];
    }
    return $out;
}

/** Encode structured attendees for DB storage (JSON array). */
function encode_attendees(array $attendees): string
{
    return json_encode(array_values($attendees), JSON_UNESCAPED_UNICODE) ?: '[]';
}

/**
 * Decode attendees from DB. Supports structured objects and legacy plain strings.
 *
 * @return list<array{first:string,last:string,box:string}>
 */
function decode_attendees(?string $raw): array
{
    if ($raw === null || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [];
    }
    $out = [];
    foreach ($data as $row) {
        if (is_array($row)) {
            $first = trim((string) ($row['first'] ?? ''));
            $last  = trim((string) ($row['last'] ?? ''));
            $box   = trim((string) ($row['box'] ?? ''));
            if ($first !== '' || $last !== '' || $box !== '') {
                $out[] = ['first' => $first, 'last' => $last, 'box' => $box];
            }
            continue;
        }
        $name = trim((string) $row);
        if ($name !== '') {
            $out[] = ['first' => $name, 'last' => '', 'box' => ''];
        }
    }
    return $out;
}

/** Full display name from an attendee record. */
function attendee_full_name(array $attendee): string
{
    return trim(($attendee['first'] ?? '') . ' ' . ($attendee['last'] ?? ''));
}

/**
 * Format one attendee for display/export.
 * e.g. "John Smith (A)" or "Jane Doe (none)" or legacy "John Smith"
 */
function format_attendee_line(array $attendee): string
{
    $name = attendee_full_name($attendee);
    if ($name === '') {
        return '';
    }
    $box = trim((string) ($attendee['box'] ?? ''));
    if ($box === '') {
        return $name;
    }
    return $name . ' (' . $box . ')';
}

/**
 * Decode attendee names from DB (JSON array or empty).
 * Returns human-readable lines (includes lunch choice when stored).
 *
 * @return list<string>
 */
function decode_attendee_names(?string $raw): array
{
    $out = [];
    foreach (decode_attendees($raw) as $attendee) {
        $line = format_attendee_line($attendee);
        if ($line !== '') {
            $out[] = $line;
        }
    }
    return $out;
}
