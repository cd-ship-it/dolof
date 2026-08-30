<?php
/**
 * Google OAuth callback. Exchanges the code, reads the account email, and
 * grants an admin session only if the email is in ADMIN_WHITELIST.
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/logger.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

auth_start_session();

$code = $_GET['code'] ?? '';
if ($code === '') {
    header('Location: ' . APP_URL . '/admin', true, 302);
    exit;
}

function google_post(string $url, array $fields): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return is_string($res) ? (json_decode($res, true) ?: []) : [];
}

$token = google_post('https://oauth2.googleapis.com/token', [
    'code'          => $code,
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => GOOGLE_REDIRECT_URL,
    'grant_type'    => 'authorization_code',
]);

if (empty($token['access_token'])) {
    app_log('high', 'Admin', 'google token exchange failed', []);
    header('Location: ' . APP_URL . '/admin?denied=1', true, 302);
    exit;
}

$ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token['access_token']],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
]);
$userInfo = json_decode((string) curl_exec($ch), true) ?: [];
curl_close($ch);

$email     = strtolower(trim((string) ($userInfo['email'] ?? '')));
$verified  = !empty($userInfo['email_verified']);
$whitelist = array_map('strtolower', email_list(ADMIN_WHITELIST));

if ($email !== '' && $verified && in_array($email, $whitelist, true)) {
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_email']     = $email;
    app_log('high', 'Admin', 'login ok', ['email' => $email]);
    header('Location: ' . APP_URL . '/admin/dashboard', true, 302);
    exit;
}

app_log('high', 'Admin', 'login denied', ['email' => $email]);
header('Location: ' . APP_URL . '/admin?denied=1', true, 302);
exit;
