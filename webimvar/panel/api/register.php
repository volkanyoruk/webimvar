<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

// Validation
if (empty($email) || empty($password)) {
    jsonResponse(['error' => 'Email ve şifre gerekli'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'Geçerli email adresi girin'], 400);
}

if (strlen($password) < 6) {
    jsonResponse(['error' => 'Şifre en az 6 karakter olmalı'], 400);
}

try {
    // Email kontrolü
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        jsonResponse(['error' => 'Bu email zaten kayıtlı'], 400);
    }
    
    // Kullanıcı oluştur
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, status) VALUES (?, ?, 'active')");
    $stmt->execute([$email, $hashedPassword]);
    
    $userId = $pdo->lastInsertId();
    
    jsonResponse([
        'success' => true,
        'message' => 'Kayıt başarılı',
        'user_id' => $userId
    ]);
    
} catch (Exception $e) {
    jsonResponse(['error' => 'Kayıt sırasında hata oluştu'], 500);
}
?>