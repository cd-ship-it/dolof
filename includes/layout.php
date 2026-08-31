<?php
/**
 * Shared page chrome. Tailwind is a compiled, self-hosted stylesheet
 * (css/app.css) — no external CDN or web-font request, so the page renders on
 * iOS Safari even behind Private Relay / content blockers. Rebuild after
 * changing markup or the inline class strings in <script>:
 *   npx tailwindcss -c tailwind.config.js -i css/input.css -o css/app.css --minify
 */
require_once __DIR__ . '/helpers.php';

function layout_head(string $title = 'Deacons Ordination Lunch Ordering Form'): void
{
    $base = defined('APP_URL') ? APP_URL : '';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?></title>
  <link rel="stylesheet" href="<?= e(asset_url('css/app.css')) ?>">
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-indigo-50 to-sky-100 text-gray-900">
<header class="bg-white border-b border-gray-200">
  <div class="max-w-3xl mx-auto px-4 py-4 flex flex-col items-center gap-2 text-center">
    <a href="<?= e($base) ?>/order">
      <img src="<?= e($base) ?>/img/xpt-logo.png" alt="Crosspoint Church 匯點教會" class="h-12 sm:h-14 w-auto">
    </a>
    <span class="font-bold text-indigo-800 text-base sm:text-lg">Deacons Ordination Lunch Ordering Form</span>
  </div>
</header>
<main class="max-w-3xl mx-auto px-4 py-8">
<?php
}

function admin_head(string $title, string $active = ''): void
{
    $base = defined('APP_URL') ? APP_URL : '';
    $links = [
        'dashboard' => ['Dashboard', $base . '/admin/dashboard'],
        'orders'    => ['Orders',    $base . '/admin/orders'],
        'report'    => ['Report',    $base . '/admin/report'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> — Dolos Admin</title>
  <link rel="stylesheet" href="<?= e(asset_url('css/app.css')) ?>">
</head>
<body class="min-h-screen bg-gray-100 text-gray-900">
<nav class="bg-white border-b border-gray-200 sticky top-0 z-40 shadow-sm">
  <div class="max-w-6xl mx-auto px-4 flex items-center justify-between h-12">
    <div class="flex items-center gap-1">
      <?php foreach ($links as $key => [$label, $href]): ?>
        <a href="<?= e($href) ?>" class="px-3 py-1.5 rounded text-sm font-medium <?= $key === $active ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
    <a href="<?= e($base) ?>/admin/logout" class="text-sm text-gray-500 hover:underline">Logout</a>
  </div>
</nav>
<main class="max-w-6xl mx-auto px-4 py-8">
<?php
}

function layout_footer(): void
{
    $base = defined('APP_URL') ? APP_URL : '';
    ?>
</main>
<footer class="max-w-3xl mx-auto px-4 py-8 text-center text-sm text-gray-500">
  <p class="flex items-center justify-center gap-1.5 text-gray-400">
    <span>Payments powered &amp; secured by</span>
    <img src="<?= e($base) ?>/img/stripe.svg" alt="Stripe" class="h-4 w-auto inline-block align-middle">
  </p>
  <p class="mt-3">
    Questions? Email <a href="mailto:cd@crosspointchurchsv.org" class="text-indigo-600 underline">cd@crosspointchurchsv.org</a><br>
    &copy; <?= date('Y') ?> Crosspoint Church
  </p>
</footer>
</body>
</html>
<?php
}
