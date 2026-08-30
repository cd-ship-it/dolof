<?php
/**
 * Load .env and expose app configuration as constants.
 * The parser trims keys and values, so both "Key=value" and "Key = value" work.
 */

$envPath = __DIR__ . '/.env';
if (!is_file($envPath)) {
    throw new RuntimeException('.env file not found at ' . $envPath);
}

foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') {
        continue;
    }
    if (strpos($line, '=') === false) {
        continue;
    }
    [$key, $value] = explode('=', $line, 2);
    $key   = trim($key);
    $value = trim($value, " \t\"'");
    if (!array_key_exists($key, $_ENV)) {
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

date_default_timezone_set('America/Los_Angeles');

function env(string $key, $default = null)
{
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

function env_bool(string $key, bool $default = false): bool
{
    $v = strtolower(trim((string) env($key, $default ? '1' : '0')));
    return in_array($v, ['1', 'true', 'yes', 'on'], true);
}

define('APP_ENV', env('APP_ENV', 'development'));
define('APP_URL', rtrim(env('APP_URL', 'http://localhost'), '/'));

define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', ''));
define('DB_USER', env('DB_USER', ''));
define('DB_PASSWORD', env('DB_PASSWORD', ''));

define('STRIPE_PUBLIC_KEY',   trim(env('StripePublicKey',   '') ?: env('STRIPE_PUBLIC_KEY',   '')));
define('STRIPE_SECRET_KEY',   trim(env('StripeSecretKey',   '') ?: env('STRIPE_SECRET_KEY',   '')));
define('STRIPE_WEBHOOK_SECRET', trim(env('StripeWebhookSecret', '') ?: env('STRIPE_WEBHOOK_SECRET', '')));

define('GOOGLE_CLIENT_ID',     env('GOOGLE_CLIENT_ID', ''));
define('GOOGLE_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET', ''));
define('GOOGLE_REDIRECT_URL',  APP_URL . '/admin/google-callback');
define('ADMIN_WHITELIST',      env('ADMIN_WHITELIST', ''));

define('DOLOS_DEFAULT_BOX_PRICE_CENTS', max(0, (int) round((float) env('DOLOS_DEFAULT_BOX_PRICE', 15) * 100)));
define('DOLOS_DEFAULT_BOX_CAP',         max(0, (int) env('DOLOS_DEFAULT_BOX_CAP', 100)));
define('DOLOS_MAX_QTY_PER_BOX',         max(1, (int) env('DOLOS_MAX_QTY_PER_BOX', 10)));

// Pending-order hold. Keep >= 30 so it outlives the Stripe Checkout session.
define('HOLD_MINUTES', max(1, (int) env('HOLD_MINUTES', 30)));
// Stripe Checkout session lifetime (Stripe minimum is 30 minutes).
define('STRIPE_CHECKOUT_MINUTES', max(30, HOLD_MINUTES));
