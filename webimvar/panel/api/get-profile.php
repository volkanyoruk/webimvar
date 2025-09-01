<?php
require_once 'config.php';

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Giriş yapmanız gerekli'], 401);
}

try {
    $stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    jsonResponse([
        'success' => true,
        'profile' => $profile
    ]);
    
} catch (Exception $e) {
    jsonResponse(['error' => 'Profil getirme hatası'], 500);
}
?>