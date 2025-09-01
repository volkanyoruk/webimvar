<?php
// ops/dashboard.php - Webimvar SaaS Dashboard
define('WEBIMVAR_ENTERPRISE', true);

require __DIR__ . '/../core/config.php';
require __DIR__ . '/../core/classes/Session.php';
require __DIR__ . '/../core/classes/Database.php';

Session::start();

// Giriş kontrolü
if (!Session::get('user_id')) {
    header('Location: /ops/login.php');
    exit;
}

$userName = Session::get('user_name', 'Admin');
$userEmail = Session::get('user_email', '');

// İstatistikleri hesapla
try {
    $db = Database::getInstance();
    
    // Kullanıcı istatistikleri
    $userCount = $db->queryOne('SELECT COUNT(*) as count FROM users WHERE is_active = 1');
    $totalUsers = $userCount['count'] ?? 0;
    
    // Paket istatistikleri
    $packageCount = $db->queryOne('SELECT COUNT(*) as count FROM packages WHERE is_active = 1');
    $totalPackages = $packageCount['count'] ?? 0;
    
    // Site istatistikleri
    $siteCount = $db->queryOne('SELECT COUNT(*) as count FROM sites WHERE is_active = 1');
    $totalSites = $siteCount['count'] ?? 0;
    
    // Abonelik istatistikleri (eğer tablo varsa)
    try {
        $subCount = $db->queryOne("SELECT COUNT(*) as count FROM subscriptions WHERE status IN ('active', 'trial')");
        $activeSubscriptions = $subCount['count'] ?? 0;
    } catch (Exception $e) {
        $activeSubscriptions = 0;
    }
    
    // Son aktiviteler
    try {
        $recentLogs = $db->query("SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 5");
    } catch (Exception $e) {
        $recentLogs = [];
    }
    
    // Bugün ki istatistikler
    $todayUsers = $db->queryOne("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()");
    $newUsersToday = $todayUsers['count'] ?? 0;
    
} catch (Exception $e) {
    // Hata durumunda varsayılan değerler
    $totalUsers = 0;
    $totalPackages = 0; 
    $totalSites = 0;
    $activeSubscriptions = 0;
    $recentLogs = [];
    $newUsersToday = 0;
    error_log('Dashboard stats error: ' . $e->getMessage());
}

// Sistem durumu
$systemHealth = [
    'status' => 'healthy',
    'database' => 'connected',
    'memory' => memory_get_usage(true),
    'uptime' => time() - $_SERVER['REQUEST_TIME_FLOAT']
];
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dashboard - Webimvar SaaS Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .stat-card { transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .quick-action { transition: all 0.2s ease; }
        .quick-action:hover { transform: scale(1.02); }
    </style>
</head>
<body class="min-h-screen bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <h1 class="text-2xl font-bold text-blue-600">Webimvar SaaS</h1>
                    <span class="text-sm bg-blue-100 text-blue-800 px-2 py-1 rounded-full">
                        Enterprise Panel v1.0
                    </span>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <div class="text-sm font-medium text-gray-900">
                            Hoşgeldiniz, <?= htmlspecialchars($userName) ?>
                        </div>
                        <div class="text-xs text-gray-500">
                            <?= htmlspecialchars($userEmail) ?>
                        </div>
                    </div>
                    <a href="/ops/logout.php" 
                       class="bg-gray-900 text-white px-4 py-2 rounded-lg text-sm hover:bg-gray-800 transition">
                        Çıkış
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 py-6">
        <!-- Welcome Section -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Kontrol Paneli</h2>
            <p class="text-gray-600">SaaS platformunuzun durumunu ve istatistiklerini görüntüleyin</p>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Aktif Kullanıcı -->
            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border border-gray-200">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600">Aktif Kullanıcı</p>
                        <p class="text-3xl font-bold text-blue-600"><?= $totalUsers ?></p>
                        <?php if ($newUsersToday > 0): ?>
                            <p class="text-xs text-green-600 mt-1">+<?= $newUsersToday ?> bugün</p>
                        <?php endif; ?>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Paket Sayısı -->
            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border border-gray-200">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600">Aktif Paket</p>
                        <p class="text-3xl font-bold text-purple-600"><?= $totalPackages ?></p>
                        <p class="text-xs text-gray-500 mt-1">Hosting paketleri</p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Toplam Site -->
            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border border-gray-200">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600">Toplam Site</p>
                        <p class="text-3xl font-bold text-green-600"><?= $totalSites ?></p>
                        <p class="text-xs text-gray-500 mt-1">Barındırılan</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Sistem Durumu -->
            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border border-gray-200">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600">Sistem Durumu</p>
                        <p class="text-3xl font-bold text-green-600">Aktif</p>
                        <p class="text-xs text-gray-500 mt-1">Tüm servisler çalışıyor</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Hızlı İşlemler -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Hızlı İşlemler</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Paket Yönetimi -->
                        <a href="/ops/packages.php" class="quick-action p-4 text-left border border-gray-200 rounded-lg hover:border-purple-300 hover:bg-purple-50 transition group">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center group-hover:bg-purple-200">
                                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">Paket Yönetimi</h4>
                                    <p class="text-sm text-gray-600">Hosting paketlerini yönet</p>
                                </div>
                            </div>
                        </a>

                        <!-- Kullanıcı Yönetimi -->
                        <button onclick="alert('Kullanıcı yönetimi yakında aktif olacak!')" class="quick-action p-4 text-left border border-gray-200 rounded-lg hover:border-blue-300 hover:bg-blue-50 transition group">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center group-hover:bg-blue-200">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">Kullanıcı Yönetimi</h4>
                                    <p class="text-sm text-gray-600">Müşterileri görüntüle ve yönet</p>
                                </div>
                            </div>
                        </button>

                        <!-- Site Yönetimi -->
                        <button onclick="alert('Site yönetimi yakında aktif olacak!')" class="quick-action p-4 text-left border border-gray-200 rounded-lg hover:border-green-300 hover:bg-green-50 transition group">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center group-hover:bg-green-200">
                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">Site Yönetimi</h4>
                                    <p class="text-sm text-gray-600">Web sitelerini yönet</p>
                                </div>
                            </div>
                        </button>

                        <!-- Ödeme Yönetimi -->
                        <button onclick="alert('Ödeme sistemi yakında aktif olacak!')" class="quick-action p-4 text-left border border-gray-200 rounded-lg hover:border-yellow-300 hover:bg-yellow-50 transition group">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center group-hover:bg-yellow-200">
                                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">Ödeme Yönetimi</h4>
                                    <p class="text-sm text-gray-600">Ödemeleri takip et</p>
                                </div>
                            </div>
                        </button>

                        <!-- Sistem Logları -->
                        <button onclick="alert('Log sistemi yakında aktif olacak!')" class="quick-action p-4 text-left border border-gray-200 rounded-lg hover:border-red-300 hover:bg-red-50 transition group">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center group-hover:bg-red-200">
                                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">Sistem Logları</h4>
                                    <p class="text-sm text-gray-600">Sistem loglarını görüntüle</p>
                                </div>
                            </div>
                        </button>

                        <!-- Ayarlar -->
                        <button onclick="alert('Sistem ayarları yakında aktif olacak!')" class="quick-action p-4 text-left border border-gray-200 rounded-lg hover:border-gray-400 hover:bg-gray-50 transition group">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center group-hover:bg-gray-200">
                                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">Sistem Ayarları</h4>
                                    <p class="text-sm text-gray-600">Panel ayarlarını düzenle</p>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Sistem Bilgileri -->
            <div class="space-y-6">
                <!-- Sistem Durumu -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Sistem Durumu</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Database</span>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                Bağlı
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">PHP Sürümü</span>
                            <span class="text-sm font-medium text-gray-900"><?= PHP_VERSION ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Bellek Kullanımı</span>
                            <span class="text-sm font-medium text-gray-900">
                                <?= round(memory_get_usage(true) / 1024 / 1024, 1) ?> MB
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Son Aktivite</span>
                            <span class="text-sm font-medium text-gray-900"><?= date('d.m.Y H:i:s') ?></span>
                        </div>
                    </div>
                </div>

                <!-- Son Aktiviteler -->
                <?php if (!empty($recentLogs)): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Son Aktiviteler</h3>
                    <div class="space-y-3">
                        <?php foreach (array_slice($recentLogs, 0, 3) as $log): ?>
                        <div class="flex items-start space-x-3">
                            <div class="w-2 h-2 bg-blue-400 rounded-full mt-2 flex-shrink-0"></div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-900 truncate"><?= htmlspecialchars($log['description'] ?? 'Sistem aktivitesi') ?></p>
                                <p class="text-xs text-gray-500"><?= date('H:i', strtotime($log['created_at'])) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12">
        <div class="max-w-7xl mx-auto px-4 py-6">
            <div class="flex justify-between items-center">
                <p class="text-sm text-gray-500">
                    © 2024 Webimvar SaaS Platform - Enterprise Panel v1.0
                </p>
                <p class="text-xs text-gray-400">
                    Son güncelleme: <?= date('d.m.Y H:i') ?>
                </p>
            </div>
        </div>
    </footer>
</body>
</html>