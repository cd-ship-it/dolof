<?php
/**
 * Order-form i18n. Locale from ?lang=en|zh (default zh), remembered in session.
 * Catalog: lang/ui.json — edit strings there.
 */

const DOLOS_LANG_SESSION_KEY = 'dolos_lang';

function i18n_locale(): string
{
    static $locale = null;
    if ($locale !== null) {
        return $locale;
    }

    $raw = '';
    if (isset($_GET['lang'])) {
        $raw = strtolower(trim((string) $_GET['lang']));
    } elseif (!empty($_SESSION[DOLOS_LANG_SESSION_KEY])) {
        $raw = strtolower(trim((string) $_SESSION[DOLOS_LANG_SESSION_KEY]));
    }

    $locale = ($raw === 'en') ? 'en' : 'zh';
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION[DOLOS_LANG_SESSION_KEY] = $locale;
    }
    return $locale;
}

/** @return array<string, array{zh?:string,en?:string}> */
function i18n_catalog(): array
{
    static $catalog = null;
    if ($catalog !== null) {
        return $catalog;
    }
    $path = dirname(__DIR__) . '/lang/ui.json';
    if (!is_file($path)) {
        $catalog = [];
        return $catalog;
    }
    $data = json_decode((string) file_get_contents($path), true);
    $catalog = is_array($data) ? $data : [];
    return $catalog;
}

/**
 * Translate a catalog key for the current locale.
 * Placeholders: t('key', ['date' => '…']) replaces {date}.
 */
function t(string $key, array $replace = []): string
{
    $entry = i18n_catalog()[$key] ?? null;
    $locale = i18n_locale();
    $s = '';
    if (is_array($entry)) {
        $s = (string) ($entry[$locale] ?? $entry['zh'] ?? $entry['en'] ?? '');
    }
    if ($s === '') {
        $s = $key;
    }
    foreach ($replace as $k => $v) {
        $s = str_replace('{' . $k . '}', (string) $v, $s);
    }
    return $s;
}

/** Flat map of all keys → current-locale string (for JS). */
function i18n_js_bundle(): array
{
    $out = [];
    $locale = i18n_locale();
    foreach (i18n_catalog() as $key => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $s = (string) ($entry[$locale] ?? $entry['zh'] ?? $entry['en'] ?? '');
        if ($s !== '') {
            $out[$key] = $s;
        }
    }
    return $out;
}

/** Order page URL with lang (and optional cancelled) query. */
function order_lang_url(string $lang, bool $cancelled = false): string
{
    $base = defined('APP_URL') ? APP_URL : '';
    $q = ['lang' => ($lang === 'en') ? 'en' : 'zh'];
    if ($cancelled) {
        $q['cancelled'] = '1';
    }
    return $base . '/order?' . http_build_query($q);
}

/** Localized dish name for box code (e.g. A–E); falls back to DB name. */
function box_localized_name(string $code, string $fallback = ''): string
{
    $key = 'dish.' . $code;
    if (isset(i18n_catalog()[$key])) {
        return t($key);
    }
    return $fallback !== '' ? $fallback : $code;
}

/** English dish name from catalog (empty if unknown). */
function dish_english_name(string $code): string
{
    $entry = i18n_catalog()['dish.' . $code] ?? null;
    if (!is_array($entry)) {
        return '';
    }
    return trim((string) ($entry['en'] ?? ''));
}

/**
 * Shorten an English dish name to at most $max characters:
 * first word(s) + "....." + last 1–2 words. If already short, return as-is.
 * Prefers whole words (no mid-word cuts) when possible.
 */
function dish_en_abbrev(string $en, int $max = 30): string
{
    $en = trim(preg_replace('/\s+/', ' ', $en) ?? '');
    // Always shorten "with" → "w/" (case-insensitive, whole word).
    $en = preg_replace('/\bwith\b/i', 'w/', $en) ?? $en;
    $en = trim(preg_replace('/\s+/', ' ', $en) ?? '');
    if ($en === '') {
        return '';
    }
    if (strlen($en) <= $max) {
        return $en;
    }

    $words = preg_split('/\s+/', $en) ?: [];
    $n = count($words);
    if ($n < 2) {
        return substr($en, 0, max(1, $max - 3)) . '...';
    }

    $ellipsis = '.....';
    $ellLen = strlen($ellipsis);
    $best = '';

    for ($tailCount = 1; $tailCount <= 2; $tailCount++) {
        if ($n <= $tailCount) {
            continue;
        }
        $tail = implode(' ', array_slice($words, -$tailCount));
        $budget = $max - $ellLen - strlen($tail);
        if ($budget < 1) {
            continue;
        }
        $headWords = array_slice($words, 0, $n - $tailCount);
        $headParts = [];
        $used = 0;
        foreach ($headWords as $w) {
            $add = ($headParts === [] ? 0 : 1) + strlen($w);
            if ($used + $add > $budget) {
                break;
            }
            $headParts[] = $w;
            $used += $add;
        }
        if ($headParts === []) {
            continue; // skip mid-word truncation when a better option exists
        }
        $candidate = implode(' ', $headParts) . $ellipsis . $tail;
        if (strlen($candidate) > $max) {
            continue;
        }
        // Prefer more characters shown (clearer), then fewer ellipsis gaps via longer head.
        if ($best === '' || strlen($candidate) > strlen($best) || (
            strlen($candidate) === strlen($best) && strlen(implode(' ', $headParts)) > strlen(explode($ellipsis, $best)[0] ?? '')
        )) {
            $best = $candidate;
        }
    }

    if ($best !== '') {
        return $best;
    }

    // Last resort: truncate first word + last word.
    $tail = $words[$n - 1];
    $budget = $max - $ellLen - strlen($tail);
    if ($budget >= 1) {
        return substr($words[0], 0, $budget) . $ellipsis . $tail;
    }
    return substr($en, 0, max(1, $max - 3)) . '...';
}
