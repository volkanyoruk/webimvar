<?php
// /public_html/ops/login.php
define('WEBIMVAR_ENTERPRISE', true);
require __DIR__ . '/../core/config.php';
require __DIR__ . '/../core/classes/Session.php';

Session::start();

/** Basit yardımcılar */
function redirect($path) {
    header('Location: ' . $path);
    exit;
}
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DatabaseConfig::HOST . ';dbname=' . DatabaseConfig::DATABASE . ';charset=' . DatabaseConfig::CHARSET;
        $pdo = new PDO($dsn, DatabaseConfig::USERNAME, DatabaseConfig::PASSWORD, DatabaseConfig::OPTIONS);
    }
    return $pdo;
}

/** CSRF token hazırla */
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

/** Zaten girişliyse dashboard'a gönder */
if (Session::has('user_id')) {
    redirect('/ops/dashboard');
}

/** POST ise giriş dene */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postCsrf  = $_POST['csrf'] ?? '';
    $email     = trim((string)($_POST['email'] ?? ''));
    $password  = (string)($_POST['password'] ?? '');

    // CSRF
    if (!$postCsrf || !hash_equals($_SESSION['csrf'], $postCsrf)) {
        Session::flash('err', 'Geçersiz istek veya CSRF eşleşmedi.');
        redirect('/ops/login');
    }

    // basit validasyon
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        Session::flash('err', 'E-posta veya şifre hatalı.');
        redirect('/ops/login');
    }

    // kullanıcıyı çek
    $sql = "SELECT id, email, password, is_active, name 
            FROM users 
            WHERE email = ? 
            LIMIT 1";
    $stmt = db()->prepare($sql);
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || (int)$user['is_active'] !== 1) {
        Session::flash('err', 'Kullanıcı bulunamadı veya pasif.');
        redirect('/ops/login');
    }
    if (!password_verify($password, $user['password'])) {
        Session::flash('err', 'E-posta veya şifre hatalı.');
        redirect('/ops/login');
    }

    // başarı: session set + id yenile + csrf yenile
    Session::regenerate();
    Session::set('user_id', (int)$user['id']);
    Session::set('user_email', $user['email']);
    Session::set('user_name', $user['name'] ?? 'Admin');
    $_SESSION['csrf'] = bin2hex(random_bytes(16));

    redirect('/ops/dashboard');
}

/** GET: formu göster */
$err = Session::getFlash('err');
?><!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Giriş — Webimvar Ops</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
  <header class="bg-white border-b">
    <div class="max-w-3xl mx-auto px-4 py-3 flex justify-between items-center">
      <div class="font-semibold">Webimvar Ops</div>
      <form action="/ops/logout" method="post">
        <button class="text-sm px-3 py-1 rounded bg-slate-900 text-white">Çıkış</button>
      </form>
    </div>
  </header>

  <main class="max-w-3xl mx-auto px-4">
    <div class="mx-auto max-w-md bg-white p-6 rounded-xl shadow mt-8">
      <h1 class="text-xl font-semibold mb-4">Yönetici Girişi</h1>

      <?php if ($err): ?>
        <div class="mb-3 rounded bg-red-50 text-red-700 px-3 py-2 text-sm">
          <?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <form method="post" action="/ops/login" class="grid gap-3">
        <input name="email" type="email" required placeholder="E-posta"
               class="border rounded px-3 py-2">
        <input name="password" type="password" required placeholder="Şifre"
               class="border rounded px-3 py-2">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <button class="rounded bg-slate-900 text-white px-4 py-2">Giriş</button>
      </form>
    </div>
  </main>
</body>
</html>