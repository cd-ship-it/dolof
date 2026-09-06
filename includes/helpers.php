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
 * Decode attendee names from DB (JSON array or empty).
 *
 * @return list<string>
 */
function decode_attendee_names(?string $raw): array
{
    if ($raw === null || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [];
    }
    $out = [];
    foreach ($data as $name) {
        $name = trim((string) $name);
        if ($name !== '') {
            $out[] = $name;
        }
    }
    return $out;
}
