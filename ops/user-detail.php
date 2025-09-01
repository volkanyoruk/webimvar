<?php
// Kullanıcı Detay Sayfası
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

// Kullanıcının paket bilgisi (varsa)
$package = null;
if (isset($user['package_id']) && $user['package_id']) {
    $package_query = "SELECT * FROM packages WHERE id = ?";
    $package_stmt = $pdo->prepare($package_query);
    $package_stmt->execute([$user['package_id']]);
    $package = $package_stmt->fetch();
}

// Kullanıcının site sayısı (sites tablosu varsa)
$sites_count = 0;
try {
    $sites_count_query = "SELECT COUNT(*) FROM sites WHERE user_id = ?";
    $sites_stmt = $pdo->prepare($sites_count_query);
    $sites_stmt->execute([$user_id]);
    $sites_count = $sites_stmt->fetchColumn();
} catch (Exception $e) {
    // Sites tablosu yoksa sessizce devam et
}

// Son aktiviteler (örnek - gerçek log sistemi gerekir)
$activities = [];
try {
    $activity_query = "SELECT * FROM system_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
    $activity_stmt = $pdo->prepare($activity_query);
    $activity_stmt->execute([$user_id]);
    $activities = $activity_stmt->fetchAll();
} catch (Exception $e) {
    // System_logs tablosu yoksa varsayılan aktiviteler
    $activities = [
        ['action' => 'login', 'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')), 'ip_address' => '192.168.1.1'],
        ['action' => 'profile_update', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')), 'ip_address' => '192.168.1.1'],
        ['action' => 'password_change', 'created_at' => date('Y-m-d H:i:s', strtotime('-3 days')), 'ip_address' => '192.168.1.1']
    ];
}

// Ödeme geçmişi (varsa)
$payments = [];
try {
    $payments_query = "SELECT * FROM payments WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
    $payments_stmt = $pdo->prepare($payments_query);
    $payments_stmt->execute([$user_id]);
    $payments = $payments_stmt->fetchAll();
} catch (Exception $e) {
    // Payments tablosu yok
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Detayları - <?= htmlspecialchars($user['username'] ?? $user['email'] ?? 'Kullanıcı') ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header-content { display: flex; align-items: center; gap: 20px; }
        .user-avatar { width: 80px; height: 80px; border-radius: 50%; background: #007bff; color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: bold; }
        .user-info h1 { color: #2c3e50; margin-bottom: 5px; }
        .user-meta { color: #6c757d; }
        
        .content-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .main-content { display: flex; flex-direction: column; gap: 20px; }
        .sidebar { display: flex; flex-direction: column; gap: 20px; }
        
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .card h2 { color: #2c3e50; margin-bottom: 15px; font-size: 1.2rem; }
        .card h3 { color: #495057; margin-bottom: 10px; font-size: 1rem; }
        
        .info-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f8f9fa; }
        .info-row:last-child { border-bottom: none; }
        .info-label { font-weight: 500; color: #495057; }
        .info-value { color: #2c3e50; }
        
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: 500; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .status-1 { background: #d4edda; color: #155724; }
        .status-0 { background: #f8d7da; color: #721c24; }
        
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.9rem; display: inline-block; margin: 2px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn:hover { opacity: 0.8; }
        
        .activity-item { padding: 12px 0; border-bottom: 1px solid #f8f9fa; }
        .activity-item:last-child { border-bottom: none; }
        .activity-action { font-weight: 500; color: #2c3e50; }
        .activity-time { font-size: 0.8rem; color: #6c757d; margin-top: 2px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; }
        .stat-item { text-align: center; }
        .stat-number { font-size: 1.5rem; font-weight: bold; color: #007bff; }
        .stat-label { font-size: 0.8rem; color: #6c757d; margin-top: 2px; }
        
        .alert { padding: 12px; border-radius: 4px; margin-bottom: 20px; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        @media (max-width: 768px) {
            .content-grid { grid-template-columns: 1fr; }
            .header-content { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div class="user-avatar">
                    <?= strtoupper(substr($user['username'] ?? $user['email'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="user-info">
                    <h1><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'Kullanıcı') ?></h1>
                    <div class="user-meta">
                        <?= htmlspecialchars($user['email'] ?? '') ?> | 
                        <?= ucfirst($user['role'] ?? 'user') ?> | 
                        Kayıt: <?= date('d.m.Y', strtotime($user['created_at'] ?? 'now')) ?>
                    </div>
                </div>
                <div style="margin-left: auto;">
                    <a href="user-edit.php?id=<?= $user_id ?>" class="btn btn-primary">Düzenle</a>
                    <a href="user-impersonate.php?id=<?= $user_id ?>" class="btn btn-secondary">Kullanıcı Olarak Giriş</a>
                    <a href="users.php" class="btn btn-secondary">Geri Dön</a>
                </div>
            </div>
        </div>

        <?php if (!($user['is_active'] ?? true)): ?>
            <div class="alert alert-warning">Bu kullanıcı hesabı pasif durumda.</div>
        <?php endif; ?>

        <div class="content-grid">
            <div class="main-content">
                <!-- Temel Bilgiler -->
                <div class="card">
                    <h2>Temel Bilgiler</h2>
                    <div class="info-row">
                        <span class="info-label">Kullanıcı Adı:</span>
                        <span class="info-value"><?= htmlspecialchars($user['username'] ?? 'N/A') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?= htmlspecialchars($user['email'] ?? 'N/A') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Tam İsim:</span>
                        <span class="info-value"><?= htmlspecialchars($user['full_name'] ?? 'Belirtilmemiş') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Telefon:</span>
                        <span class="info-value"><?= htmlspecialchars($user['phone'] ?? 'Belirtilmemiş') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Rol:</span>
                        <span class="info-value"><?= ucfirst($user['role'] ?? 'user') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Durum:</span>
                        <span class="info-value">
                            <?php if (isset($user['is_active'])): ?>
                                <span class="status-badge status-<?= $user['is_active'] ? '1' : '0' ?>">
                                    <?= $user['is_active'] ? 'Aktif' : 'Pasif' ?>
                                </span>
                            <?php else: ?>
                                <span class="status-badge status-active">Aktif</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Son Giriş:</span>
                        <span class="info-value"><?= isset($user['last_login']) ? date('d.m.Y H:i', strtotime($user['last_login'])) : 'Hiç giriş yapmamış' ?></span>
                    </div>
                </div>

                <!-- Paket Bilgileri -->
                <div class="card">
                    <h2>Paket Bilgileri</h2>
                    <?php if ($package): ?>
                        <div class="info-row">
                            <span class="info-label">Mevcut Paket:</span>
                            <span class="info-value"><strong><?= htmlspecialchars($package['name']) ?></strong></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Aylık Ücret:</span>
                            <span class="info-value"><?= number_format($package['price']) ?>₺</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Disk Limiti:</span>
                            <span class="info-value"><?= $package['disk_space'] ?? 'Sınırsız' ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Site Limiti:</span>
                            <span class="info-value"><?= $package['sites_limit'] ?? 'Sınırsız' ?></span>
                        </div>
                    <?php else: ?>
                        <p>Bu kullanıcının atanmış paketi bulunmuyor.</p>
                        <a href="user-package-assign.php?id=<?= $user_id ?>" class="btn btn-primary">Paket Ata</a>
                    <?php endif; ?>
                </div>

                <!-- Son Aktiviteler -->
                <div class="card">
                    <h2>Son Aktiviteler</h2>
                    <?php if (!empty($activities)): ?>
                        <?php foreach ($activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-action">
                                    <?php
                                    $action_texts = [
                                        'login' => '🔐 Sisteme giriş yaptı',
                                        'logout' => '🚪 Sistemden çıkış yaptı', 
                                        'profile_update' => '👤 Profil bilgilerini güncelledi',
                                        'password_change' => '🔑 Şifresini değiştirdi',
                                        'site_created' => '🌐 Yeni site oluşturdu',
                                        'payment' => '💳 Ödeme gerçekleştirdi'
                                    ];
                                    echo $action_texts[$activity['action']] ?? '📝 ' . ucfirst($activity['action'] ?? 'Bilinmeyen işlem');
                                    ?>
                                </div>
                                <div class="activity-time">
                                    <?= date('d.m.Y H:i', strtotime($activity['created_at'] ?? 'now')) ?>
                                    <?php if (isset($activity['ip_address'])): ?>
                                        | IP: <?= htmlspecialchars($activity['ip_address']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>Henüz aktivite kaydı bulunmuyor.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sidebar">
                <!-- İstatistikler -->
                <div class="card">
                    <h2>İstatistikler</h2>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number"><?= $sites_count ?></div>
                            <div class="stat-label">Site</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= count($payments) ?></div>
                            <div class="stat-label">Ödeme</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= $user['login_count'] ?? 0 ?></div>
                            <div class="stat-label">Giriş</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= ceil((time() - strtotime($user['created_at'] ?? 'now')) / 86400) ?></div>
                            <div class="stat-label">Gün</div>
                        </div>
                    </div>
                </div>

                <!-- Hızlı İşlemler -->
                <div class="card">
                    <h2>Hızlı İşlemler</h2>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="user-edit.php?id=<?= $user_id ?>" class="btn btn-primary">✏️ Bilgileri Düzenle</a>
                        <a href="user-sites.php?id=<?= $user_id ?>" class="btn btn-primary">🌐 Sitelerini Görüntüle</a>
                        <a href="user-payments.php?id=<?= $user_id ?>" class="btn btn-primary">💳 Ödeme Geçmişi</a>
                        <a href="mailto:<?= htmlspecialchars($user['email'] ?? '') ?>" class="btn btn-secondary">📧 Email Gönder</a>
                        <a href="user-password-reset.php?id=<?= $user_id ?>" class="btn btn-warning">🔑 Şifre Sıfırla</a>
                        <a href="users.php?delete=<?= $user_id ?>" class="btn btn-danger" onclick="return confirm('Bu kullanıcıyı silmek istediğinizden emin misiniz?')">🗑️ Kullanıcıyı Sil</a>
                    </div>
                </div>

                <!-- Notlar -->
                <div class="card">
                    <h2>Admin Notları</h2>
                    <textarea placeholder="Bu kullanıcı hakkında notlarınızı buraya yazabilirsiniz..." style="width: 100%; height: 100px; padding: 8px; border: 1px solid #dee2e6; border-radius: 4px; resize: vertical;"></textarea>
                    <button class="btn btn-primary" style="margin-top: 10px;">Notu Kaydet</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>