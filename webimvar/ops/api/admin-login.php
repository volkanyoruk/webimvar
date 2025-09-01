<?php
// Hata raporlamayı aç (debug için)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session başlat
session_start();

// Config dosyasını dahil et
$configPath = realpath(__DIR__ . '/../../api/config.php');
if (!$configPath || !file_exists($configPath)) {
    die(json_encode(['error' => 'Config dosyası bulunamadı: ' . __DIR__ . '/../../api/config.php']));
}

require_once $configPath;

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Sadece POST kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// POST verilerini al
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$username = trim($data['username'] ?? '');
$password = trim($data['password'] ?? '');

// Validation
if (empty($username)) {
    echo json_encode(['error' => 'Kullanıcı adı gerekli']);
    exit;
}

if (empty($password)) {
    echo json_encode(['error' => 'Şifre gerekli']);
    exit;
}

try {
    // Admin kullanıcısını kontrol et
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        echo json_encode(['error' => 'Kullanıcı bulunamadı']);
        exit;
    }
    
    // Şifre kontrolü
    if (!password_verify($password, $admin['password_hash'])) {
        echo json_encode(['error' => 'Şifre hatalı']);
        exit;
    }
    
    // Session bilgilerini set et
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_role'] = $admin['role'];
    
    // Son giriş zamanını güncelle
    $updateStmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
    $updateStmt->execute([$admin['id']]);
    
    // Başarılı response
    echo json_encode([
        'success' => true,
        'message' => 'Giriş başarılı',
        'user' => [
            'id' => $admin['id'],
            'username' => $admin['username'],
            'role' => $admin['role']
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Veritabanı hatası: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Sistem hatası: ' . $e->getMessage()]);
}
?>