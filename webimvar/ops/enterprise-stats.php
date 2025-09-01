<?php
require_once '../../api/config.php';

session_start();
if (!isset($_SESSION['admin_id'])) {
    jsonResponse(['error' => 'Yetkisiz erişim'], 401);
}

try {
    // Toplam aktif kullanıcılar
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
    $stmt->execute();
    $activeUsers = $stmt->fetch()['count'];
    
    // Bu ay toplam gelir
    $stmt = $pdo->prepare("
        SELECT SUM(p.price) as revenue 
        FROM users u 
        JOIN packages pkg ON u.package_id = pkg.id 
        JOIN payments pay ON u.id = pay.user_id 
        WHERE pay.status = 'completed' 
        AND pay.payment_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
    ");
    $stmt->execute();
    $monthlyRevenue = $stmt->fetch()['revenue'] ?: 0;
    
    // ARPU (Average Revenue Per User)
    $arpu = $activeUsers > 0 ? round($monthlyRevenue / $activeUsers, 2) : 0;
    
    // Churn rate hesaplama (basit simulasyon)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM users 
        WHERE status = 'suspended' 
        AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $churned = $stmt->fetch()['count'];
    $churnRate = $activeUsers > 0 ? round(($churned / $activeUsers) * 100, 1) : 0;
    
    // Paket dağılımı
    $stmt = $pdo->prepare("
        SELECT 
            pkg.name,
            pkg.slug,
            COUNT(u.id) as user_count
        FROM packages pkg
        LEFT JOIN users u ON pkg.id = u.package_id AND u.status = 'active'
        WHERE pkg.is_active = TRUE
        GROUP BY pkg.id, pkg.name, pkg.slug
        ORDER BY pkg.sort_order
    ");
    $stmt->execute();
    $packageData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $packageDistribution = [
        'basic' => 0,
        'premium' => 0,
        'enterprise' => 0
    ];
    
    foreach ($packageData as $pkg) {
        $packageDistribution[$pkg['slug']] = (int)$pkg['user_count'];
    }
    
    // Site durumları
    $stmt = $pdo->prepare("
        SELECT 
            site_status,
            COUNT(*) as count
        FROM user_profiles 
        WHERE site_status IS NOT NULL
        GROUP BY site_status
    ");
    $stmt->execute();
    $siteStatuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $siteStats = [
        'pending' => 0,
        'creating' => 0,
        'ready' => 0,
        'active' => 0
    ];
    
    foreach ($siteStatuses as $status) {
        $siteStats[$status['site_status']] = (int)$status['count'];
    }
    
    // Growth metrics
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $newUsersLast30Days = $stmt->fetch()['count'];
    
    // Haftalık büyüme trendi (son 8 hafta)
    $stmt = $pdo->prepare("
        SELECT 
            WEEK(created_at) as week_num,
            COUNT(*) as signups
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 8 WEEK)
        GROUP BY WEEK(created_at)
        ORDER BY week_num DESC
        LIMIT 8
    ");
    $stmt->execute();
    $weeklyGrowth = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse([
        'success' => true,
        'stats' => [
            'totalRevenue' => (float)$monthlyRevenue,
            'activeUsers' => (int)$activeUsers,
            'churnRate' => $churnRate,
            'arpu' => $arpu,
            'newUsersLast30Days' => (int)$newUsersLast30Days,
            'packageDistribution' => $packageDistribution,
            'siteStats' => $siteStats,
            'weeklyGrowth' => $weeklyGrowth
        ],
        'generated_at' => date('c')
    ]);
    
} catch (Exception $e) {
    jsonResponse(['error' => 'İstatistik yüklenirken hata: ' . $e->getMessage()], 500);
}
?>