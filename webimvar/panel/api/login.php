<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    jsonResponse(['error' => 'Email ve şifre gerekli'], 400);
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        
        // Profil bilgilerini al
        $stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        jsonResponse([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'status' => $user['status']
            ],
            'profile' => $profile
        ]);
    } else {
        jsonResponse(['error' => 'Email veya şifre hatalı'], 401);
    }
    
} catch (Exception $e) {
    jsonResponse(['error' => 'Giriş sırasında hata oluştu'], 500);
}
?>