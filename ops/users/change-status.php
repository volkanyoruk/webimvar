<?php
// ops/users/change-status.php - AJAX kullanıcı durum değiştirme
define('WEBIMVAR_ENTERPRISE', true);

require __DIR__ . '/../../core/config.php';
require __DIR__ . '/../../core/classes/Session.php';
require __DIR__ . '/../../core/classes/Database.php';
require __DIR__ . '/../../core/classes/Response.php';
require __DIR__ . '/../models/User.php';

Session::start();

// Sadece POST istekleri kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::json(['error' => 'Method not allowed'], 405);
}

// Giriş kontrolü
if (!Session::get('user_id')) {
    Response::json(['error' => 'Unauthorized'], 401);
}

// CSRF token kontrolü
$sessionToken = Session::get('csrf_token');
$postToken = $_POST['csrf_token'] ?? '';

if (!$sessionToken || !hash_equals($sessionToken, $postToken)) {
    Response::json(['error' => 'CSRF token invalid'], 400);
}

// Parametreleri al
$userId = (int)($_POST['user_id'] ?? 0);
$newStatus = (int)($_POST['is_active'] ?? 0);

// Validasyon
if ($userId <= 0) {
    Response::json(['error' => 'Geçersiz kullanıcı ID'], 400);
}

if (!in_array($newStatus, [0, 1])) {
    Response::json(['error' => 'Geçersiz durum değeri'], 400);
}

try {
    $userModel = new User();
    
    // Kullanıcının var olup olmadığını kontrol et
    $existingUser = $userModel->find($userId);
    if (!$existingUser) {
        Response::json(['error' => 'Kullanıcı bulunamadı'], 404);
    }

    // Admin kullanıcısını pasif yapmayı engelle (güvenlik)
    if ($existingUser['role'] === 'admin' && $newStatus === 0) {
        Response::json(['error' => 'Admin kullanıcıyı pasif yapamazsınız'], 403);
    }

    // Durumu güncelle
    $result = $userModel->updateById($userId, [
        'is_active' => $newStatus
    ]);

    if ($result) {
        $statusText = $newStatus ? 'aktif' : 'pasif';
        
        // Log kaydı (opsiyonel)
        try {
            Database::getInstance()->insert('system_logs', [
                'user_id' => Session::get('user_id'),
                'action' => 'user_status_change',
                'module' => 'users',
                'description' => "Kullanıcı #{$userId} durumu {$statusText} olarak değiştirildi",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            // Log hatası önemli değil, devam et
        }

        Response::json([
            'success' => true,
            'message' => "Kullanıcı durumu {$statusText} olarak güncellendi.",
            'user_id' => $userId,
            'new_status' => $newStatus
        ]);
    } else {
        Response::json(['error' => 'Kullanıcı durumu güncellenemedi'], 500);
    }

} catch (Exception $e) {
    // Hata logla
    error_log("User status change error: " . $e->getMessage());
    
    Response::json([
        'error' => 'Güncelleme sırasında hata oluştu: ' . $e->getMessage()
    ], 500);
}
?>