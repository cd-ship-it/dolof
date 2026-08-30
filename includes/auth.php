<?php
/**
 * Admin session guard + CSRF helpers. Admin auth is Google-OAuth only
 * (see admin/index.php and admin/google-callback.php).
 */
if (!defined('APP_URL')) {
    require_once dirname(__DIR__) . '/config.php';
}

function auth_start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function require_admin(): void
{
    auth_start_session();
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: ' . APP_URL . '/admin', true, 302);
        exit;
    }
    csrf_generate();
}

function admin_logout(): void
{
    auth_start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function csrf_generate(): string
{
    auth_start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_generate(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_is_valid(): bool
{
    auth_start_session();
    return !empty($_SESSION['csrf_token'])
        && !empty($_POST['csrf_token'])
        && hash_equals((string) $_SESSION['csrf_token'], (string) $_POST['csrf_token']);
}

function csrf_verify(): void
{
    if (!csrf_is_valid()) {
        http_response_code(403);
        exit('Forbidden — invalid CSRF token. Go back and reload the form.');
    }
}
