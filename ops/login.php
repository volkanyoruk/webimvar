<?php
// ops/login.php - Direkt login dosyası
define('WEBIMVAR_ENTERPRISE', true);

require __DIR__ . '/../core/config.php';
require __DIR__ . '/../core/classes/Session.php';
require __DIR__ . '/../core/classes/Database.php';

Session::start();

// Zaten giriş yaptıysa dashboard'a yönlendir
if (Session::get('user_id')) {
    header('Location: /ops/dashboard.php');
    exit;
}

// CSRF token
$csrf = Session::get('csrf', '');
if (!$csrf) {
    $csrf = bin2hex(random_bytes(16));
    Session::set('csrf', $csrf);
}

// POST işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postCsrf = $_POST['csrf'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // CSRF kontrol
    if (!hash_equals($csrf, $postCsrf)) {
        Session::flash('err', 'Güvenlik hatası.');
        header('Location: /ops/login.php');
        exit;
    }
    
    // Basit validasyon
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        Session::flash('err', 'E-posta ve şifre gerekli.');
        header('Location: /ops/login.php');
        exit;
    }
    
    // Database kontrol
    try {
        $db = Database::getInstance();
        $user = $db->queryOne(
            'SELECT id, email, full_name, password, is_active FROM users WHERE email = ? LIMIT 1',
            [$email]
        );
        
        if ($user && (int)$user['is_active'] === 1 && password_verify($password, $user['password'])) {
            Session::regenerate();
            Session::set('user_id', (int)$user['id']);
            Session::set('user_email', $user['email']);
            Session::set('user_name', $user['full_name'] ?? 'Admin');
            header('Location: /ops/dashboard.php');
            exit;
        } else {
            Session::flash('err', 'Geçersiz kullanıcı bilgileri.');
        }
    } catch (Exception $e) {
        Session::flash('err', 'Sistem hatası: ' . $e->getMessage());
        error_log('Login error: ' . $e->getMessage());
    }
    
    header('Location: /ops/login.php');
    exit;
}

$errorMsg = Session::getFlash('err');
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Giriş - Webimvar Ops</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-100">
    <div class="flex items-center justify-center min-h-screen">
        <div class="w-full max-w-md bg-white rounded-lg shadow-md p-6">
            <h1 class="text-2xl font-bold text-center mb-6">Webimvar Ops</h1>
            
            <?php if ($errorMsg): ?>
                <div class="mb-4 p-3 bg-red-100 text-red-700 rounded text-sm">
                    <?= htmlspecialchars($errorMsg) ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="/ops/login.php">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">E-posta</label>
                    <input type="email" name="email" required 
                           class="w-full px-3 py-2 border rounded focus:outline-none focus:border-blue-500">
                </div>
                
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Şifre</label>
                    <input type="password" name="password" required 
                           class="w-full px-3 py-2 border rounded focus:outline-none focus:border-blue-500">
                </div>
                
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                
                <button type="submit" class="w-full bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600">
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