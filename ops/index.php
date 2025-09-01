<?php
// Webimvar Ops - Complete System
declare(strict_types=1);

define('WEBIMVAR_ENTERPRISE', true);

require __DIR__ . '/../core/config.php';
require __DIR__ . '/../core/classes/Session.php';
require __DIR__ . '/../core/classes/Database.php';

Session::start();

// Helper functions
function path_now(): string {
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $qpos = strpos($uri, '?');
    if ($qpos !== false) $uri = substr($uri, 0, $qpos);
    $uri = rtrim($uri, '/') ?: '/';
    return $uri === '/ops' ? '/ops/' : $uri;
}

function redirect(string $to): void {
    header('Location: ' . $to, true, 302);
    exit;
}

function csrf_get(): string {
    $t = Session::get('csrf', '');
    if (!$t) {
        $t = bin2hex(random_bytes(16));
        Session::set('csrf', $t);
    }
    return $t;
}

function csrf_ok(): bool {
    $sess = (string) Session::get('csrf', '');
    $post = (string) ($_POST['csrf'] ?? '');
    return ($sess !== '' && $post !== '' && hash_equals($sess, $post));
}

// Main routing
$path = path_now();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Root redirect
if ($path === '/ops/') {
    if (Session::get('user_id')) redirect('/ops/dashboard');
    redirect('/ops/login');
}

// LOGIN PAGE (GET)
if ($path === '/ops/login' && $method === 'GET') {
    if (Session::get('user_id')) redirect('/ops/dashboard');
    
    $title = 'Giriş';
    $csrf = csrf_get();
    $err = Session::getFlash('err');
    ?>
    <!doctype html>
    <html lang="tr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Giriş — Webimvar Ops</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="min-h-screen bg-slate-100">
        <div class="flex items-center justify-center min-h-screen">
            <div class="w-full max-w-md bg-white p-6 rounded-xl shadow-lg">
                <h1 class="text-2xl font-bold text-center mb-6">Webimvar Ops</h1>
                
                <?php if ($err): ?>
                    <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg text-sm">
                        <?= htmlspecialchars($err) ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="/ops/login" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">E-posta</label>
                        <input name="email" type="email" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Şifre</label>
                        <input name="password" type="password" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <button class="w-full py-2 px-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Giriş Yap
                    </button>
                </form>
                
                <div class="mt-4 text-xs text-gray-500 text-center">
                    Test: admin@webimvar.com / YeniGüçlü!123
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// LOGIN POST
if ($path === '/ops/login' && $method === 'POST') {
    if (!csrf_ok()) {
        Session::flash('err', 'Geçersiz güvenlik anahtarı.');
        redirect('/ops/login');
    }
    
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '';
    $pass = (string) ($_POST['password'] ?? '');
    
    if ($email === '' || $pass === '') {
        Session::flash('err', 'E-posta ve şifre zorunlu.');
        redirect('/ops/login');
    }
    
    try {
        $db = Database::getInstance();
        $user = $db->queryOne(
            'SELECT id, email, full_name, password, is_active FROM users WHERE email = ? LIMIT 1',
            [$email]
        );
        
        if ($user && (int)$user['is_active'] === 1 && password_verify($pass, $user['password'])) {
            Session::regenerate();
            Session::set('user_id', (int)$user['id']);
            Session::set('user_email', $user['email']);
            Session::set('user_name', $user['full_name'] ?? 'Admin');
            redirect('/ops/dashboard');
        }
    } catch (Exception $e) {
        error_log('Login error: ' . $e->getMessage());
    }
    
    Session::flash('err', 'Geçersiz e-posta veya şifre.');
    redirect('/ops/login');
}

// DASHBOARD (protected)
if ($path === '/ops/dashboard') {
    if (!Session::get('user_id')) redirect('/ops/login');
    
    $userName = Session::get('user_name', 'Admin');
    ?>
    <!doctype html>
    <html lang="tr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Dashboard — Webimvar Ops</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="min-h-screen bg-slate-50">
        <header class="bg-white border-b">
            <div class="max-w-6xl mx-auto px-4 py-3 flex justify-between items-center">
                <h1 class="text-xl font-semibold">Webimvar Ops</h1>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-600">Hoşgeldiniz, <?= htmlspecialchars($userName) ?></span>
                    <a href="/ops/logout" class="px-3 py-1 bg-gray-900 text-white rounded text-sm">Çıkış</a>
                </div>
            </div>
        </header>
        
        <main class="max-w-6xl mx-auto px-4 py-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white p-6 rounded-xl shadow">
                    <div class="text-sm text-gray-500">Aktif Kullanıcı</div>
                    <div class="text-2xl font-semibold mt-1">1</div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow">
                    <div class="text-sm text-gray-500">Toplam Site</div>
                    <div class="text-2xl font-semibold mt-1">—</div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow">
                    <div class="text-sm text-gray-500">Aktif Oturum</div>
                    <div class="text-2xl font-semibold mt-1">1</div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow">
                    <div class="text-sm text-gray-500">Sistem Durumu</div>
                    <div class="text-2xl font-semibold mt-1 text-green-600">Aktif</div>
                </div>
            </div>
            
            <div class="mt-6">
                <h2 class="text-lg font-semibold mb-4">Hızlı İşlemler</h2>
                <div class="flex gap-2">
                    <button class="px-4 py-2 bg-blue-600 text-white rounded">Kullanıcılar</button>
                    <button class="px-4 py-2 bg-gray-600 text-white rounded">Site Yönetimi</button>
                    <button class="px-4 py-2 bg-green-600 text-white rounded">Loglar</button>
                </div>
            </div>
        </main>
    </body>
    </html>
    <?php
    exit;
}

// LOGOUT
if ($path === '/ops/logout') {
    Session::destroy();
    redirect('/ops/login');
}

// 404
http_response_code(404);
echo 'Not Found: ' . htmlspecialchars($path);