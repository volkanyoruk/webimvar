<?php
// Kullanıcı Impersonation (Support için)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/classes/DatabaseConfig.php';

$user_id = (int)($_GET['id'] ?? 0);
if (!$user_id) {
    header('Location: users.php');
    exit;
}

try {
    $config = DatabaseConfig::getConfig();
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}", 
        $config['username'], 
        $config['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Kullanıcı bilgilerini getir
$user_query = "SELECT * FROM users WHERE id = ?";
$user_stmt = $pdo->prepare($user_query);
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

if (!$user) {
    header('Location: users.php');
    exit;
}

session_start();

// Impersonation işlemi
if (isset($_POST['start_impersonation'])) {
    // Mevcut admin bilgilerini sakla
    $_SESSION['original_admin_id'] = $_SESSION['user_id'] ?? 1;
    $_SESSION['original_admin_username'] = $_SESSION['username'] ?? 'admin';
    $_SESSION['impersonating'] = true;
    
    // Hedef kullanıcı olarak giriş yap
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'] ?? $user['email'];
    $_SESSION['user_email'] = $user['email'] ?? '';
    $_SESSION['user_role'] = $user['role'] ?? 'user';
    
    // Log kaydı
    try {
        $log_query = "INSERT INTO system_logs (user_id, action, details, ip_address, created_at) VALUES (?, 'impersonation_start', ?, ?, NOW())";
        $log_stmt = $pdo->prepare($log_query);
        $log_stmt->execute([
            $user_id,
            "Admin impersonation started for user: {$user['username']} (ID: {$user['id']})",
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        // Log tablosu yoksa sessizce devam et
    }
    
    // Ana siteye yönlendir
    header('Location: ../index.php');
    exit;
}

// Impersonation'ı sonlandır
if (isset($_GET['end_impersonation']) && isset($_SESSION['impersonating'])) {
    $original_admin_id = $_SESSION['original_admin_id'] ?? 1;
    
    // Log kaydı
    try {
        $log_query = "INSERT INTO system_logs (user_id, action, details, ip_address, created_at) VALUES (?, 'impersonation_end', ?, ?, NOW())";
        $log_stmt = $pdo->prepare($log_query);
        $log_stmt->execute([
            $_SESSION['user_id'],
            "Admin impersonation ended, returned to admin ID: $original_admin_id",
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        // Log tablosu yoksa sessizce devam et
    }
    
    // Admin session'ını geri yükle
    $_SESSION['user_id'] = $original_admin_id;
    $_SESSION['username'] = $_SESSION['original_admin_username'];
    unset($_SESSION['original_admin_id']);
    unset($_SESSION['original_admin_username']);
    unset($_SESSION['impersonating']);
    
    header('Location: users.php');
    exit;
}

// Son impersonation geçmişi
$impersonation_history = [];
try {
    $history_query = "SELECT * FROM system_logs WHERE user_id = ? AND action IN ('impersonation_start', 'impersonation_end') ORDER BY created_at DESC LIMIT 10";
    $history_stmt = $pdo->prepare($history_query);
    $history_stmt->execute([$user_id]);
    $impersonation_history = $history_stmt->fetchAll();
} catch (Exception $e) {
    // System_logs tablosu yok
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Impersonation - <?= htmlspecialchars($user['username'] ?? $user['email']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f9fa; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { color: #2c3e50; margin-bottom: 5px; }
        .header p { color: #6c757d; }
        
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card h2 { color: #2c3e50; margin-bottom: 15px; font-size: 1.2rem; }
        
        .user-info { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 20px; }
        .user-avatar { width: 60px; height: 60px; border-radius: 50%; background: #007bff; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; }
        .user-details h3 { color: #2c3e50; margin-bottom: 5px; }
        .user-details p { color: #6c757d; margin: 2px 0; }
        
        .warning-box { background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .warning-box h3 { margin-bottom: 10px; color: #856404; }
        .warning-box ul { margin-left: 20px; margin-top: 10px; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.95rem; display: inline-block; margin: 5px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn:hover { opacity: 0.9; }
        .btn-large { padding: 15px 30px; font-size: 1.1rem; }
        
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .info-item { background: #e9ecef; padding: 15px; border-radius: 8px; }
        .info-label { font-weight: 600; color: #495057; margin-bottom: 5px; }
        .info-value { color: #2c3e50; }
        
        .history-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .history-table th, .history-table td { padding: 10px; text-align: left; border-bottom: 1px solid #dee2e6; }
        .history-table th { background: #f8f9fa; font-weight: 600; }
        .history-table tr:hover { background: #f8f9fa; }
        
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: 500; }
        .status-start { background: #d4edda; color: #155724; }
        .status-end { background: #f8d7da; color: #721c24; }
        
        .current-session { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        
        @media (max-width: 768px) {
            .user-info { flex-direction: column; text-align: center; }
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Kullanıcı Impersonation</h1>
            <p>Destek amaçlı kullanıcı hesabına giriş sistemi</p>
        </div>

        <?php if (isset($_SESSION['impersonating'])): ?>
        <div class="current-session">
            <h3>Şu anda impersonation aktif</h3>
            <p>Kullanıcı ID <?= $_SESSION['user_id'] ?> olarak sisteme giriş yapmış durumdasınız.</p>
            <a href="?end_impersonation=1" class="btn btn-danger">Impersonation'ı Sonlandır</a>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2>Hedef Kullanıcı Bilgileri</h2>
            
            <div class="user-info">
                <div class="user-avatar">
                    <?= strtoupper(substr($user['username'] ?? $user['email'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="user-details">
                    <h3><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'Kullanıcı') ?></h3>
                    <p><strong>Email:</strong> <?= htmlspecialchars($user['email'] ?? 'N/A') ?></p>
                    <p><strong>Kullanıcı Adı:</strong> <?= htmlspecialchars($user['username'] ?? 'N/A') ?></p>
                    <p><strong>Rol:</strong> <?= ucfirst($user['role'] ?? 'user') ?></p>
                    <p><strong>Durum:</strong> <?= isset($user['is_active']) && $user['is_active'] ? 'Aktif' : 'Pasif' ?></p>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Kullanıcı ID</div>
                    <div class="info-value"><?= $user['id'] ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Kayıt Tarihi</div>
                    <div class="info-value"><?= date('d.m.Y H:i', strtotime($user['created_at'] ?? 'now')) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Son Giriş</div>
                    <div class="info-value"><?= isset($user['last_login']) ? date('d.m.Y H:i', strtotime($user['last_login'])) : 'Hiç giriş yapmamış' ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Giriş Sayısı</div>
                    <div class="info-value"><?= $user['login_count'] ?? 0 ?></div>
                </div>
            </div>
        </div>

        <div class="warning-box">
            <h3>⚠️ Dikkat: Impersonation Kuralları</h3>
            <p>Bu özellik sadece destek amaçlı kullanılmalıdır ve şu kurallara uyulmalıdır:</p>
            <ul>
                <li>Sadece kullanıcının izni ile veya destek talebi durumunda kullanın</li>
                <li>Kullanıcının kişisel bilgilerine gereksiz erişim sağlamayın</li>
                <li>İşlem tamamlandığında hemen impersonation'ı sonlandırın</li>
                <li>Tüm impersonation aktiviteleri sistem loglarında kayıt altına alınır</li>
                <li>Bu özelliği kötüye kullanmak hesap kapatılmasına neden olabilir</li>
            </ul>
        </div>

        <div class="card">
            <h2>Impersonation İşlemi</h2>
            
            <?php if (!isset($_SESSION['impersonating'])): ?>
                <form method="POST" onsubmit="return confirm('Bu kullanıcı olarak sisteme giriş yapmak istediğinizden emin misiniz?\n\nKullanıcı: <?= htmlspecialchars($user['username'] ?? $user['email']) ?>\nEmail: <?= htmlspecialchars($user['email'] ?? '') ?>')">
                    <p>Bu işlem sizi <?= htmlspecialchars($user['username'] ?? $user['email']) ?> kullanıcısı olarak sisteme giriş yapacak.</p>
                    <div style="margin: 20px 0;">
                        <button type="submit" name="start_impersonation" class="btn btn-warning btn-large">
                            🔐 Kullanıcı Olarak Giriş Yap
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="current-session">
                    <p>Şu anda başka bir kullanıcı olarak giriş yapmış durumdasınız. Önce mevcut impersonation'ı sonlandırın.</p>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 30px;">
                <a href="user-detail.php?id=<?= $user_id ?>" class="btn btn-primary">👁️ Kullanıcı Detayları</a>
                <a href="user-edit.php?id=<?= $user_id ?>" class="btn btn-secondary">✏️ Kullanıcıyı Düzenle</a>
                <a href="users.php" class="btn btn-secondary">👥 Kullanıcı Listesi</a>
            </div>
        </div>

        <?php if (!empty($impersonation_history)): ?>
        <div class="card">
            <h2>Impersonation Geçmişi</h2>
            
            <table class="history-table">
                <thead>
                    <tr>
                        <th>İşlem</th>
                        <th>Tarih</th>
                        <th>IP Adresi</th>
                        <th>Detaylar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($impersonation_history as $log): ?>
                        <tr>
                            <td>
                                <span class="status-badge status-<?= $log['action'] == 'impersonation_start' ? 'start' : 'end' ?>">
                                    <?= $log['action'] == 'impersonation_start' ? 'Başlatıldı' : 'Sonlandırıldı' ?>
                                </span>
                            </td>
                            <td><?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?></td>
                            <td><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($log['details'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2>Güvenlik Bilgileri</h2>
            <div style="color: #6c757d; line-height: 1.6;">
                <p><strong>IP Adresiniz:</strong> <?= $_SERVER['REMOTE_ADDR'] ?? 'unknown' ?></p>
                <p><strong>Tarayıcı:</strong> <?= $_SERVER['HTTP_USER_AGENT'] ?? 'unknown' ?></p>
                <p><strong>Zaman:</strong> <?= date('d.m.Y H:i:s') ?></p>
                <p style="margin-top: 10px;"><small>Bu bilgiler güvenlik loglarında saklanacaktır.</small></p>
            </div>
        </div>
    </div>
</body>
</html>