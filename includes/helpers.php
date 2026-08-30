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
