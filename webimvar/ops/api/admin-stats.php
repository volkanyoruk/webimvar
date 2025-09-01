<?php
require_once '../../api/config.php';

session_start();
if (!isset($_SESSION['admin_id'])) {
    jsonResponse(['error' => 'Yetkisiz erişim'], 401);
}

try {
    // Toplam kullanıcı
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users");
    $stmt->execute();
    $totalUsers = $stmt->fetch()['total'];
    
    // Aktif siteler
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM user_profiles WHERE site_status IN ('ready', 'active')");
    $stmt->execute();
    $activeSites = $stmt->fetch()['total'];
    
    // Bekleyen siteler
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM user_profiles WHERE site_status IN ('pending', 'creating')");
    $stmt->execute();
    $pendingSites = $stmt->fetch()['total'];
    
    // Aylık gelir (örnek hesaplama)
    $monthlyRevenue = $totalUsers * 99;
    
    jsonResponse([
        'success' => true,
        'stats' => [
            'totalUsers' => $totalUsers,
            'activeSites' => $activeSites,
            'pendingSites' => $pendingSites,
            'monthlyRevenue' => $monthlyRevenue
        ]
    ]);
    
} catch (Exception $e) {
    jsonResponse(['error' => 'İstatistik yüklenirken hata oluştu'], 500);
}
?>