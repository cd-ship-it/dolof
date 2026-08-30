<?php
/**
 * Shared page chrome. Tailwind is loaded from the Play CDN (small internal app).
 */
require_once __DIR__ . '/helpers.php';

function layout_head(string $title = 'Deacons Ordination Luncheon'): void
{
    $base = defined('APP_URL') ? APP_URL : '';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'DM Sans', ui-sans-serif, system-ui, sans-serif; }
    .card { background:#fff; border:1px solid #e5e7eb; border-radius:.75rem; padding:1.5rem; box-shadow:0 1px 2px rgba(0,0,0,.04); }
    .btn-primary { background:#4f46e5; color:#fff; font-weight:600; padding:.625rem 1.25rem; border-radius:.5rem; }
    .btn-primary:hover { background:#4338ca; }
    .btn-primary:disabled { opacity:.5; cursor:not-allowed; }
  </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-indigo-50 to-sky-100 text-gray-900">
<header class="bg-white border-b border-gray-200">
  <div class="max-w-3xl mx-auto px-4 py-4 flex items-center gap-3">
    <a href="<?= e($base) ?>/order" class="font-bold text-indigo-800 text-lg">Deacons Ordination Luncheon</a>
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
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> — Dolos Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
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
    ?>
</main>
<footer class="max-w-3xl mx-auto px-4 py-8 text-center text-sm text-gray-500">
  Questions? Email <a href="mailto:cd@crosspointchurchsv.org" class="text-indigo-600 underline">cd@crosspointchurchsv.org</a><br>
  &copy; <?= date('Y') ?> Crosspoint Church
</footer>
</body>
</html>
<?php
}
