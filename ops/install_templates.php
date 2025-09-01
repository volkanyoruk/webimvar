<?php
// ops/install_templates.php
ini_set('display_errors','1'); error_reporting(E_ALL);

$base = __DIR__ . '/templates';
$paths = [
  "$base",
  "$base/auth",
  "$base/layouts",
  "$base/admin",
];

// Klasörleri oluştur
foreach ($paths as $p) {
  if (!is_dir($p)) {
    mkdir($p, 0755, true);
    echo "DIR OK: $p<br>";
  } else {
    echo "DIR EXISTS: $p<br>";
  }
}

// Dosya içerikleri
$layout = <<<'PHP'
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <title><?= isset($title) ? $title.' — ' : '' ?>Webimvar Ops</title>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
  <header class="bg-white border-b">
    <div class="mx-auto max-w-6xl px-4 py-3 flex justify-between items-center">
      <div class="font-semibold">Webimvar Ops</div>
      <form method="post" action="/ops/logout">
        <button class="text-sm px-3 py-1 rounded bg-slate-900 text-white">Çıkış</button>
      </form>
    </div>
  </header>
  <main class="mx-auto max-w-6xl px-4 py-8">
    <?= $content ?? '' ?>
  </main>
</body>
</html>
PHP;

$login = <<<'PHP'
<?php $title='Giriş'; ob_start(); ?>
<div class="mx-auto max-w-md bg-white p-6 rounded-xl shadow">
  <h1 class="text-xl font-semibold mb-4">Yönetici Girişi</h1>

  <?php if ($m = Session::getFlash('err')): ?>
    <div class="mb-3 rounded bg-red-50 text-red-700 px-3 py-2 text-sm">
      <?= htmlspecialchars($m) ?>
    </div>
  <?php endif; ?>

  <form method="post" action="/ops/login" class="grid gap-3">
    <input name="email" type="email" required placeholder="E-posta" class="border rounded px-3 py-2">
    <input name="password" type="password" required placeholder="Şifre" class="border rounded px-3 py-2">
    <button class="rounded bg-slate-900 text-white px-4 py-2">Giriş</button>
  </form>
</div>
<?php $content = ob_get_clean(); include __DIR__.'/../layouts/admin.php'; ?>
PHP;

$dashboard = <<<'PHP'
<?php $title='Dashboard'; ob_start(); ?>
<?php if (!empty($flashOk)): ?>
  <div class="mb-4 rounded bg-emerald-50 text-emerald-700 px-3 py-2 text-sm">
    <?= htmlspecialchars($flashOk) ?>
  </div>
<?php endif; ?>

<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
  <div class="bg-white p-4 rounded-xl shadow">
    <div class="text-sm text-slate-500">Aktif Kullanıcı</div>
    <div class="text-2xl font-semibold mt-1">—</div>
  </div>
  <div class="bg-white p-4 rounded-xl shadow">
    <div class="text-sm text-slate-500">Aktif Site</div>
    <div class="text-2xl font-semibold mt-1">—</div>
  </div>
  <div class="bg-white p-4 rounded-xl shadow">
    <div class="text-sm text-slate-500">24s Başarısız Giriş</div>
    <div class="text-2xl font-semibold mt-1">—</div>
  </div>
  <div class="bg-white p-4 rounded-xl shadow">
    <div class="text-sm text-slate-500">Ortalama Sorgu Süresi</div>
    <div class="text-2xl font-semibold mt-1">—</div>
  </div>
</div>
<?php $content = ob_get_clean(); include __DIR__.'/../layouts/admin.php'; ?>
PHP;

// Yaz
file_put_contents("$base/layouts/admin.php", $layout);
echo "FILE OK: $base/layouts/admin.php<br>";

file_put_contents("$base/auth/login.php", $login);
echo "FILE OK: $base/auth/login.php<br>";

file_put_contents("$base/admin/dashboard.php", $dashboard);
echo "FILE OK: $base/admin/dashboard.php<br>";

echo "<hr>DONE";