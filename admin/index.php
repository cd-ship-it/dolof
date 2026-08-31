<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

auth_start_session();

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: ' . APP_URL . '/admin/dashboard', true, 302);
    exit;
}

$denied = isset($_GET['denied']);

$googleUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URL,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'access_type'   => 'online',
    'prompt'        => 'select_account',
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login — Dolos</title>
  <link rel="stylesheet" href="<?= e(asset_url('css/app.css')) ?>">
</head>
<body class="min-h-screen flex items-center justify-center bg-gray-100 px-4">
  <div class="bg-white rounded-xl shadow p-8 w-full max-w-md">
    <h1 class="text-xl font-bold text-gray-900 mb-1">Dolos Admin</h1>
    <p class="text-sm text-gray-500 mb-6">Deacons Ordination Lunch Ordering Form Ordering System</p>

    <?php if ($denied): ?>
      <p class="text-sm text-red-600 mb-4">That Google account is not authorized for admin access.</p>
    <?php endif; ?>

    <?php if (GOOGLE_CLIENT_ID): ?>
      <a href="<?= e($googleUrl) ?>"
         class="flex w-full justify-center items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
        <img src="https://www.gstatic.com/images/branding/product/1x/googleg_48dp.png" alt="" class="h-5 w-5">
        Sign in with Google
      </a>
    <?php else: ?>
      <p class="text-sm text-red-600">Google OAuth is not configured (set GOOGLE_CLIENT_ID in .env).</p>
    <?php endif; ?>
  </div>
</body>
</html>
