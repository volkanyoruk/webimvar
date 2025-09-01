<?php
// ops/packages.php - Paket yönetimi sayfası
define('WEBIMVAR_ENTERPRISE', true);

require __DIR__ . '/../core/config.php';
require __DIR__ . '/../core/classes/Session.php';
require __DIR__ . '/../core/classes/Database.php';

// Model ve Controller dosyalarını yükle
require __DIR__ . '/models/Package.php';
require __DIR__ . '/controllers/PackageController.php';

Session::start();

// Giriş kontrolü
if (!Session::get('user_id')) {
    header('Location: /ops/login.php');
    exit;
}

try {
    $controller = new PackageController();
    $controller->index();
} catch (Exception $e) {
    // Hata durumunda basit bir sayfa göster
    $error = $e->getMessage();
    ?>
    <!doctype html>
    <html lang="tr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Hata - Webimvar Ops</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="min-h-screen bg-slate-50">
        <div class="container mx-auto px-4 py-8">
            <div class="max-w-2xl mx-auto bg-white rounded-xl shadow-sm p-6">
                <h1 class="text-2xl font-bold text-red-600 mb-4">⚠️ Sistem Hatası</h1>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                    <p class="text-red-800"><?= htmlspecialchars($error) ?></p>
                </div>
                <div class="flex gap-4">
                    <a href="/ops/dashboard.php" class="bg-blue-600 text-white px-4 py-2 rounded">Dashboard'a Dön</a>
                    <button onclick="location.reload()" class="bg-gray-600 text-white px-4 py-2 rounded">Yenile</button>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>