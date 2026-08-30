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
