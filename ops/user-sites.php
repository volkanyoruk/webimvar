<?php
// Kullanıcı Siteleri Yönetimi
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

// Kullanıcının paket bilgisi
$package = null;
if (isset($user['package_id']) && $user['package_id']) {
    try {
        $package_query = "SELECT * FROM packages WHERE id = ?";
        $package_stmt = $pdo->prepare($package_query);
        $package_stmt->execute([$user['package_id']]);
        $package = $package_stmt->fetch();
    } catch (Exception $e) {
        // Packages tablosu yok
    }
}

// Sites tablosunun var olup olmadığını kontrol et
$sites = [];
$sites_table_exists = false;
$total_sites = 0;
$total_disk_usage = 0;

try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('sites', $tables)) {
        $sites_table_exists = true;
        
        // Kullanıcının sitelerini getir
        $sites_query = "SELECT * FROM sites WHERE user_id = ? ORDER BY created_at DESC";
        $sites_stmt = $pdo->prepare($sites_query);
        $sites_stmt->execute([$user_id]);
        $sites = $sites_stmt->fetchAll();
        
        $total_sites = count($sites);
        
        // Toplam disk kullanımı
        foreach ($sites as $site) {
            $total_disk_usage += ($site['disk_usage'] ?? 0);
        }
    }
} catch (Exception $e) {
    // Hata durumunda demo veriler kullan
}

// Eğer sites tablosu yoksa demo veriler oluştur
if (!$sites_table_exists) {
    $sites = [
        [
            'id' => 1,
            'domain' => 'example.com',
            'status' => 'active',
            'disk_usage' => 2048000000, // 2GB
            'bandwidth_usage' => 5120000000, // 5GB
            'created_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
            'last_backup' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'ssl_status' => 'active',
            'php_version' => '8.1'
        ],
        [
            'id' => 2,
            'domain' => 'myblog.com',
            'status' => 'active',
            'disk_usage' => 1024000000, // 1GB
            'bandwidth_usage' => 2048000000, // 2GB
            'created_at' => date('Y-m-d H:i:s', strtotime('-15 days')),
            'last_backup' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'ssl_status' => 'active',
            'php_version' => '8.2'
        ]
    ];
    $total_sites = count($sites);
    $total_disk_usage = 3072000000; // 3GB
}

$message = '';
$error = '';

// Site işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $site_id = (int)($_POST['site_id'] ?? 0);
    
    try {
        if ($sites_table_exists) {
            switch ($action) {
                case 'suspend':
                    $pdo->prepare("UPDATE sites SET status = 'suspended' WHERE id = ? AND user_id = ?")
                        ->execute([$site_id, $user_id]);
                    $message = "Site askıya alındı.";
                    break;
                    
                case 'activate':
                    $pdo->prepare("UPDATE sites SET status = 'active' WHERE id = ? AND user_id = ?")
                        ->execute([$site_id, $user_id]);
                    $message = "Site aktifleştirildi.";
                    break;
                    
                case 'delete':
                    $pdo->prepare("DELETE FROM sites WHERE id = ? AND user_id = ?")
                        ->execute([$site_id, $user_id]);
                    $message = "Site silindi.";
                    break;
                    
                case 'backup':
                    $pdo->prepare("UPDATE sites SET last_backup = NOW() WHERE id = ? AND user_id = ?")
                        ->execute([$site_id, $user_id]);
                    $message = "Site yedekleme başlatıldı.";
                    break;
            }
            
            // Güncel site listesini tekrar getir
            $sites_stmt->execute([$user_id]);
            $sites = $sites_stmt->fetchAll();
        } else {
            $message = "Demo modunda işlemler gerçekleştirilmez.";
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Disk kullanım yüzdesi
$disk_usage_percent = 0;
$disk_limit = 0;
if ($package && isset($package['disk_space'])) {
    $disk_limit = (float)$package['disk_space'] * 1024 * 1024 * 1024; // GB to bytes
    $disk_usage_percent = $disk_limit > 0 ? min(($total_disk_usage / $disk_limit) * 100, 100) : 0;
}

// Site limit kontrolü
$site_limit = $package['sites_limit'] ?? 0;
$sites_remaining = max(0, $site_limit - $total_sites);

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Siteleri - <?= htmlspecialchars($user['username'] ?? $user['email']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { color: #2c3e50; margin-bottom: 5px; }
        .header p { color: #6c757d; }
        
        .user-info { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 20px; }
        .user-avatar { width: 60px; height: 60px; border-radius: 50%; background: #007bff; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; }
        .user-details h3 { color: #2c3e50; margin-bottom: 5px; }
        .user-details p { color: #6c757d; margin: 2px 0; }
        
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-number { font-size: 1.8rem; font-weight: bold; margin-bottom: 5px; }
        .stat-label { color: #6c757d; font-size: 0.9rem; }
        .stat-sites { color: #007bff; }
        .stat-disk { color: #28a745; }
        .stat-bandwidth { color: #ffc107; }
        .stat-limit { color: #dc3545; }
        
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card h2 { color: #2c3e50; margin-bottom: 15px; font-size: 1.2rem; }
        
        .usage-bar { width: 100%; height: 20px; background: #e9ecef; border-radius: 10px; overflow: hidden; margin: 10px 0; }
        .usage-fill { height: 100%; background: linear-gradient(90deg, #28a745, #ffc107, #dc3545); border-radius: 10px; transition: width 0.3s ease; }
        .usage-text { font-size: 0.85rem; color: #6c757d; margin-top: 5px; }
        
        .sites-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; margin-top: 20px; }
        .site-card { background: white; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; transition: transform 0.2s, box-shadow 0.2s; }
        .site-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        
        .site-header { display: flex; justify-content: between; align-items: center; margin-bottom: 15px; }
        .site-domain { font-weight: 600; color: #2c3e50; font-size: 1.1rem; }
        .site-status { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 500; }
        .status-active { background: #d4edda; color: #155724; }
        .status-suspended { background: #fff3cd; color: #856404; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        
        .site-info { margin: 15px 0; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .info-label { color: #6c757d; font-size: 0.9rem; }
        .info-value { color: #2c3e50; font-weight: 500; }
        
        .site-actions { display: flex; gap: 5px; flex-wrap: wrap; margin-top: 15px; }
        .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.8rem; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .btn:hover { opacity: 0.9; }
        
        .message { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .no-sites { text-align: center; padding: 40px; color: #6c757d; }
        .no-sites h3 { margin-bottom: 10px; }
        
        @media (max-width: 768px) {
            .user-info { flex-direction: column; text-align: center; }
            .stats { grid-template-columns: repeat(2, 1fr); }
            .sites-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="header">
            <h1>Kullanıcı Siteleri</h1>
            <p><?= htmlspecialchars($user['username'] ?? $user['email']) ?> kullanıcısının site yönetimi</p>
        </div>

        <div class="user-info">
            <div class="user-avatar">
                <?= strtoupper(substr($user['username'] ?? $user['email'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="user-details">
                <h3><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'Kullanıcı') ?></h3>
                <p><strong>Email:</strong> <?= htmlspecialchars($user['email'] ?? 'N/A') ?></p>
                <p><strong>Paket:</strong> <?= $package ? htmlspecialchars($package['name']) : 'Paket atanmamış' ?></p>
                <p><strong>Durum:</strong> <?= isset($user['is_active']) && $user['is_active'] ? 'Aktif' : 'Pasif' ?></p>
            </div>
        </div>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number stat-sites"><?= $total_sites ?></div>
                <div class="stat-label">Toplam Site</div>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-disk"><?= number_format($total_disk_usage / (1024*1024*1024), 2) ?> GB</div>
                <div class="stat-label">Disk Kullanımı</div>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-bandwidth">
                    <?php 
                    $total_bandwidth = 0;
                    foreach ($sites as $site) {
                        $total_bandwidth += ($site['bandwidth_usage'] ?? 0);
                    }
                    echo number_format($total_bandwidth / (1024*1024*1024), 2);
                    ?> GB
                </div>
                <div class="stat-label">Bant Genişliği</div>
            </div>
            <?php if ($package): ?>
            <div class="stat-card">
                <div class="stat-number stat-limit"><?= $sites_remaining ?></div>
                <div class="stat-label">Kalan Site Hakkı</div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($package): ?>
        <div class="card">
            <h2>Kaynak Kullanımı</h2>
            
            <div style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span>Disk Kullanımı</span>
                    <span><?= number_format($total_disk_usage / (1024*1024*1024), 2) ?> GB / <?= $package['disk_space'] ?? 'Sınırsız' ?> GB</span>
                </div>
                <div class="usage-bar">
                    <div class="usage-fill" style="width: <?= $disk_usage_percent ?>%"></div>
                </div>
                <div class="usage-text"><?= number_format($disk_usage_percent, 1) ?>% kullanılıyor</div>
            </div>
            
            <div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span>Site Sayısı</span>
                    <span><?= $total_sites ?> / <?= $site_limit ?: 'Sınırsız' ?></span>
                </div>
                <div class="usage-bar">
                    <div class="usage-fill" style="width: <?= $site_limit > 0 ? min(($total_sites / $site_limit) * 100, 100) : 0 ?>%"></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($sites_remaining <= 0 && $package): ?>
            <div class="alert alert-warning">
                Bu kullanıcının site ekleme hakkı dolmuş. Paket yükseltmesi gerekebilir.
            </div>
        <?php endif; ?>

        <?php if (!$sites_table_exists): ?>
            <div class="alert alert-info">
                <strong>Demo Mod:</strong> Sites tablosu bulunamadığı için demo veriler gösteriliyor. Gerçek site yönetimi için sites tablosunu oluşturun.
            </div>
        <?php endif; ?>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Site Listesi (<?= count($sites) ?>)</h2>
                <div>
                    <a href="site-add.php?user_id=<?= $user_id ?>" class="btn btn-success">Yeni Site Ekle</a>
                    <a href="user-detail.php?id=<?= $user_id ?>" class="btn btn-secondary">Kullanıcı Detayı</a>
                </div>
            </div>

            <?php if (empty($sites)): ?>
                <div class="no-sites">
                    <h3>Henüz site yok</h3>
                    <p>Bu kullanıcının henüz oluşturulmuş sitesi bulunmuyor.</p>
                    <a href="site-add.php?user_id=<?= $user_id ?>" class="btn btn-primary" style="margin-top: 15px;">İlk Siteyi Oluştur</a>
                </div>
            <?php else: ?>
                <div class="sites-grid">
                    <?php foreach ($sites as $site): 
                        $status_class = 'status-' . ($site['status'] ?? 'inactive');
                        $status_text = ucfirst($site['status'] ?? 'inactive');
                        $disk_gb = number_format(($site['disk_usage'] ?? 0) / (1024*1024*1024), 2);
                        $bandwidth_gb = number_format(($site['bandwidth_usage'] ?? 0) / (1024*1024*1024), 2);
                    ?>
                        <div class="site-card">
                            <div class="site-header">
                                <div class="site-domain"><?= htmlspecialchars($site['domain'] ?? 'domain.com') ?></div>
                                <span class="site-status <?= $status_class ?>"><?= $status_text ?></span>
                            </div>
                            
                            <div class="site-info">
                                <div class="info-row">
                                    <span class="info-label">Disk Kullanımı:</span>
                                    <span class="info-value"><?= $disk_gb ?> GB</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Bant Genişliği:</span>
                                    <span class="info-value"><?= $bandwidth_gb ?> GB</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">PHP Versiyonu:</span>
                                    <span class="info-value"><?= $site['php_version'] ?? '8.1' ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">SSL Durumu:</span>
                                    <span class="info-value"><?= ($site['ssl_status'] ?? 'active') == 'active' ? '🔒 Aktif' : '🔓 Pasif' ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Son Yedekleme:</span>
                                    <span class="info-value"><?= isset($site['last_backup']) ? date('d.m.Y H:i', strtotime($site['last_backup'])) : 'Hiç' ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Oluşturulma:</span>
                                    <span class="info-value"><?= date('d.m.Y', strtotime($site['created_at'] ?? 'now')) ?></span>
                                </div>
                            </div>
                            
                            <div class="site-actions">
                                <a href="https://<?= htmlspecialchars($site['domain']) ?>" target="_blank" class="btn btn-info">Siteyi Aç</a>
                                <a href="site-edit.php?id=<?= $site['id'] ?>" class="btn btn-primary">Düzenle</a>
                                
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="site_id" value="<?= $site['id'] ?>">
                                    <input type="hidden" name="action" value="backup">
                                    <button type="submit" class="btn btn-secondary">Yedekle</button>
                                </form>
                                
                                <?php if (($site['status'] ?? 'active') == 'active'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="site_id" value="<?= $site['id'] ?>">
                                        <input type="hidden" name="action" value="suspend">
                                        <button type="submit" class="btn btn-warning" onclick="return confirm('Bu siteyi askıya almak istediğinizden emin misiniz?')">Askıya Al</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="site_id" value="<?= $site['id'] ?>">
                                        <input type="hidden" name="action" value="activate">
                                        <button type="submit" class="btn btn-success">Aktifleştir</button>
                                    </form>
                                <?php endif; ?>
                                
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="site_id" value="<?= $site['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Bu siteyi silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!')">Sil</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="user-detail.php?id=<?= $user_id ?>" class="btn btn-primary">Kullanıcı Detayları</a>
            <a href="user-edit.php?id=<?= $user_id ?>" class="btn btn-secondary">Kullanıcıyı Düzenle</a>
            <a href="users.php" class="btn btn-secondary">Kullanıcı Listesi</a>
        </div>
    </div>
</body>
</html>