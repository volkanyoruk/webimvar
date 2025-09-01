<?php
require_once '../../api/config.php';

session_start();
if (!isset($_SESSION['admin_id'])) {
    jsonResponse(['error' => 'Yetkisiz erişim'], 401);
}

$page = $_GET['page'] ?? 1;
$search = $_GET['search'] ?? '';
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    $whereClause = '';
    $params = [];
    
    if (!empty($search)) {
        $whereClause = "WHERE u.email LIKE ? OR up.name LIKE ? OR up.profession LIKE ?";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }
    
    // Kullanıcıları getir
    $sql = "SELECT u.*, up.name, up.profession, up.subdomain, up.site_status 
            FROM users u 
            LEFT JOIN user_profiles up ON u.id = up.user_id 
            $whereClause 
            ORDER BY u.created_at DESC 
            LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Toplam sayfa sayısını hesapla
    $countSql = "SELECT COUNT(*) as total FROM users u LEFT JOIN user_profiles up ON u.id = up.user_id $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalUsers = $countStmt->fetch()['total'];
    $totalPages = ceil($totalUsers / $limit);
    
    jsonResponse([
        'success' => true,
        'users' => $users,
        'pagination' => [
            'currentPage' => (int)$page,
            'totalPages' => $totalPages,
            'totalUsers' => $totalUsers
        ]
    ]);
    
} catch (Exception $e) {
    jsonResponse(['error' => 'Kullanıcılar yüklenirken hata oluştu'], 500);
}
?>