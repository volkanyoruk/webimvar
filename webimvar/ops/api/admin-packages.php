<?php
require_once '../../api/config.php';

session_start();
if (!isset($_SESSION['admin_id'])) {
    jsonResponse(['error' => 'Yetkisiz erişim'], 401);
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            COUNT(u.id) as user_count
        FROM packages p
        LEFT JOIN users u ON p.id = u.package_id AND u.status = 'active'
        GROUP BY p.id
        ORDER BY p.sort_order, p.price
    ");
    $stmt->execute();
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse([
        'success' => true,
        'packages' => $packages
    ]);
    
} catch (Exception $e) {
    jsonResponse(['error' => 'Paketler yüklenirken hata: ' . $e->getMessage()], 500);
}
?>