<?php
// Yeni Site Ekleme Sayfası
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/classes/DatabaseConfig.php';

$user_id = (int)($_GET['user_id'] ?? 0);
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

// Mevcut site sayısı kontrolü
$current_sites = 0;
$sites_table_exists = false;
try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('sites', $tables)) {
        $sites_table_exists = true;
        $site_count_query = "SELECT COUNT(*) FROM sites WHERE user_id = ?";
        $site_count_stmt = $pdo->prepare($site_count_query);
        $site_count_stmt->execute([$user_id]);
        $current_sites = $site_count_stmt->fetchColumn();
    }
} catch (Exception $e) {
    // Sites tablosu yok
}

// Site limiti kontrolü
$site_limit = $package['sites_limit'] ?? 0;
$can_add_site = ($site_limit == 0) || ($current_sites < $site_limit);

$message = '';
$error = '';

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sites_table_exists) {
    try {
        if (!$can_add_site) {
            throw new Exception("Site limiti doldu. Paket yükseltmesi gerekiyor.");
        }
        
        $domain = trim($_POST['domain'] ?? '');
        $subdomain = trim($_POST['subdomain'] ?? '');
        $php_version = $_POST['php_version'] ?? '8.1';
        $ssl_enabled = isset($_POST['ssl_enabled']);
        $auto_backup = isset($_POST['auto_backup']);
        $status = $_POST['status'] ?? 'active';
        
        // Domain validation
        if (empty($domain)) {
            throw new Exception("Domain adı gerekli.");
        }
        
        // Domain formatını kontrol et
        if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/', $domain)) {
            throw new Exception("Geçersiz domain formatı.");
        }
        
        // Subdomain kontrolü
        $full_domain = $domain;
        if (!empty($subdomain)) {
            if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?$/', $subdomain)) {
                throw new Exception("Geçersiz subdomain formatı.");
            }
            $full_domain = $subdomain . '.' . $domain;
        }
        
        // Domain benzersizliği kontrolü
        $domain_check = $pdo->prepare("SELECT id FROM sites WHERE domain = ?");
        $domain_check->execute([$full_domain]);
        if ($domain_check->fetch()) {
            throw new Exception("Bu domain zaten kullanımda.");
        }
        
        // Sites tablosunun sütunlarını kontrol et
        $site_columns = [];
        $site_column_result = $pdo->query("DESCRIBE sites");
        while($row = $site_column_result->fetch()) {
            $site_columns[] = $row['Field'];
        }
        
        $pdo->beginTransaction();
        
        // Insert alanlarını hazırla
        $insert_fields = ['user_id', 'domain'];
        $insert_values = [$user_id, $full_domain];
        $insert_placeholders = ['?', '?'];
        
        if (in_array('php_version', $site_columns)) {
            $insert_fields[] = 'php_version';
            $insert_values[] = $php_version;
            $insert_placeholders[] = '?';
        }
        
        if (in_array('ssl_status', $site_columns)) {
            $insert_fields[] = 'ssl_status';
            $insert_values[] = $ssl_enabled ? 'active' : 'inactive';
            $insert_placeholders[] = '?';
        }
        
        if (in_array('auto_backup', $site_columns)) {
            $insert_fields[] = 'auto_backup';
            $insert_values[] = $auto_backup ? 1 : 0;
            $insert_placeholders[] = '?';
        }
        
        if (in_array('status', $site_columns)) {
            $insert_fields[] = 'status';
            $insert_values[] = $status;
            $insert_placeholders[] = '?';
        }
        
        if (in_array('disk_usage', $site_columns)) {
            $insert_fields[] = 'disk_usage';
            $insert_values[] = 0;
            $insert_placeholders[] = '?';
        }
        
        if (in_array('bandwidth_usage', $site_columns)) {
            $insert_fields[] = 'bandwidth_usage';
            $insert_values[] = 0;
            $insert_placeholders[] = '?';
        }
        
        if (in_array('created_at', $site_columns)) {
            $insert_fields[] = 'created_at';
            $insert_placeholders[] = 'NOW()';
        }
        
        if (in_array('updated_at', $site_columns)) {
            $insert_fields[] = 'updated_at';
            $insert_placeholders[] = 'NOW()';
        }
        
        $insert_sql = "INSERT INTO sites (" . implode(', ', $insert_fields) . ") VALUES (" . implode(', ', $insert_placeholders) . ")";
        $insert_stmt = $pdo->prepare($insert_sql);
        $insert_stmt->execute($insert_values);
        
        $new_site_id = $pdo->lastInsertId();
        
        // Kullanıcının site sayısını güncelle (eğer users tablosunda sites_count varsa)
        $user_columns = [];
        $user_column_result = $pdo->query("DESCRIBE users");
        while($row = $user_column_result->fetch()) {
            $user_columns[] = $row['Field'];
        }
        
        if (in_array('sites_count', $user_columns)) {
            $pdo->prepare("UPDATE users SET sites_count = sites_count + 1 WHERE id = ?")->execute([$user_id]);
        }
        
        // Log kaydı
        try {
            $log_query = "INSERT INTO system_logs (user_id, action, details, ip_address, created_at) VALUES (?, 'site_created', ?, ?, NOW())";
            $log_stmt = $pdo->prepare($log_query);
            $log_stmt->execute([
                $user_id,
                "New site created: $full_domain (ID: $new_site_id)",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            // Log tablosu yoksa sessizce devam et
        }
        
        $pdo->commit();
        $message = "Site başarıyla oluşturuldu! Domain: $full_domain";
        
        // Form alanlarını temizle
        $_POST = [];
        
    } catch (Exception $e) {
        $pdo->rollback();
        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Site Ekle - <?= htmlspecialchars($user['username'] ?? $user['email']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f9fa; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { color: #2c3e50; margin-bottom: 5px; }
        .header p { color: #6c757d; }
        
        .user-info { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 20px; }
        .user-avatar { width: 50px; height: 50px; border-radius: 50%; background: #007bff; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .user-details h3 { color: #2c3e50; margin-bottom: 5px; }
        .user-details p { color: #6c757d; margin: 2px 0; }
        
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card h2 { color: #2c3e50; margin-bottom: 15px; font-size: 1.2rem; }
        
        .form-section { margin-bottom: 25px; }
        .form-section h3 { color: #495057; margin-bottom: 15px; font-size: 1rem; border-bottom: 1px solid #e9ecef; padding-bottom: 8px; }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px; }
        .form-row.single { grid-template-columns: 1fr; }
        
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 8px; font-weight: 500; color: #495057; }
        .form-group .required { color: #dc3545; }
        .form-control { padding: 10px 12px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 0.95rem; }
        .form-control:focus { outline: none; border-color: #007bff; box-shadow: 0 0 0 2px rgba(0,123,255,0.25); }
        
        .domain-input { display: flex; gap: 5px; align-items: center; }
        .domain-input input { flex: 1; }
        .domain-separator { color: #6c757d; font-weight: bold; }
        
        .checkbox-group { display: flex; align-items: center; gap: 8px; margin: 10px 0; }
        .checkbox-group input[type="checkbox"] { transform: scale(1.2); }
        
        .select-control { padding: 10px 12px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 0.95rem; background: white; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.95rem; display: inline-block; margin: 5px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn:hover { opacity: 0.9; }
        
        .message { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .help-text { font-size: 0.85rem; color: #6c757d; margin-top: 4px; }
        
        .limit-info { background: #e7f3ff; border: 1px solid #b3d7ff; border-radius: 4px; padding: 15px; margin-bottom: 20px; }
        .limit-info h4 { color: #0056b3; margin-bottom: 10px; }
        
        .form-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e9ecef; }
        
        .domain-preview { background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 10px 0; border: 1px solid #dee2e6; }
        .preview-url { font-family: monospace; font-size: 1.1rem; color: #007bff; font-weight: bold; }
        
        @media (max-width: 768px) {
            .user-info { flex-direction: column; text-align: center; }
            .form-row { grid-template-columns: 1fr; }
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
            <h1>Yeni Site Ekle</h1>
            <p>Kullanıcı için yeni hosting sitesi oluşturun</p>
        </div>

        <div class="user-info">
            <div class="user-avatar">
                <?= strtoupper(substr($user['username'] ?? $user['email'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="user-details">
                <h3><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'Kullanıcı') ?></h3>
                <p><strong>Email:</strong> <?= htmlspecialchars($user['email'] ?? 'N/A') ?></p>
                <p><strong>Paket:</strong> <?= $package ? htmlspecialchars($package['name']) : 'Paket atanmamış' ?></p>
                <p><strong>Mevcut Siteler:</strong> <?= $current_sites ?><?= $site_limit > 0 ? " / $site_limit" : '' ?></p>
            </div>
        </div>

        <?php if (!$sites_table_exists): ?>
            <div class="alert alert-warning">
                <strong>Uyarı:</strong> Sites tablosu bulunamadı. Gerçek site oluşturma işlemleri yapılamayacak.
                Önce veritabanında 'sites' tablosunu oluşturmanız gerekiyor.
            </div>
        <?php endif; ?>

        <?php if (!$can_add_site): ?>
            <div class="alert alert-danger">
                <strong>Site Limiti Doldu!</strong> Bu kullanıcının site ekleme hakkı kalmamış.
                Mevcut limit: <?= $site_limit ?> site. Paket yükseltmesi gerekiyor.
                <div style="margin-top: 10px;">
                    <a href="user-edit.php?id=<?= $user_id ?>" class="btn btn-warning">Paketi Değiştir</a>
                </div>
            </div>
        <?php else: ?>
            
            <?php if ($package): ?>
            <div class="limit-info">
                <h4>Paket Bilgileri</h4>
                <p><strong>Paket:</strong> <?= htmlspecialchars($package['name']) ?></p>
                <p><strong>Site Limiti:</strong> <?= $site_limit > 0 ? "$current_sites / $site_limit site" : 'Sınırsız' ?></p>
                <p><strong>Disk Alanı:</strong> <?= $package['disk_space'] ?? 'Sınırsız' ?> GB</p>
                <p><strong>Kalan Hak:</strong> <?= $site_limit > 0 ? ($site_limit - $current_sites) . ' site' : 'Sınırsız' ?></p>
            </div>
            <?php endif; ?>

            <div class="card">
                <h2>Site Bilgileri</h2>
                
                <form method="POST" id="siteForm">
                    <!-- Domain Bilgileri -->
                    <div class="form-section">
                        <h3>Domain Ayarları</h3>
                        
                        <div class="form-row single">
                            <div class="form-group">
                                <label>Ana Domain <span class="required">*</span></label>
                                <input type="text" name="domain" id="domain" class="form-control" value="<?= htmlspecialchars($_POST['domain'] ?? '') ?>" required placeholder="example.com">
                                <div class="help-text">Ana domain adı (örn: example.com)</div>
                            </div>
                        </div>
                        
                        <div class="form-row single">
                            <div class="form-group">
                                <label>Subdomain (İsteğe Bağlı)</label>
                                <div class="domain-input">
                                    <input type="text" name="subdomain" id="subdomain" class="form-control" value="<?= htmlspecialchars($_POST['subdomain'] ?? '') ?>" placeholder="www">
                                    <span class="domain-separator">.</span>
                                    <span id="domainPreview" style="color: #6c757d;">domain.com</span>
                                </div>
                                <div class="help-text">Alt domain (örn: www, blog, shop)</div>
                            </div>
                        </div>
                        
                        <div class="domain-preview" id="fullDomainPreview" style="display: none;">
                            <strong>Tam URL:</strong> <span class="preview-url">https://example.com</span>
                        </div>
                    </div>

                    <!-- Teknik Ayarlar -->
                    <div class="form-section">
                        <h3>Teknik Ayarlar</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>PHP Versiyonu</label>
                                <select name="php_version" class="select-control">
                                    <option value="7.4" <?= ($_POST['php_version'] ?? '') == '7.4' ? 'selected' : '' ?>>PHP 7.4</option>
                                    <option value="8.0" <?= ($_POST['php_version'] ?? '') == '8.0' ? 'selected' : '' ?>>PHP 8.0</option>
                                    <option value="8.1" <?= ($_POST['php_version'] ?? '8.1') == '8.1' ? 'selected' : '' ?>>PHP 8.1 (Önerilen)</option>
                                    <option value="8.2" <?= ($_POST['php_version'] ?? '') == '8.2' ? 'selected' : '' ?>>PHP 8.2</option>
                                    <option value="8.3" <?= ($_POST['php_version'] ?? '') == '8.3' ? 'selected' : '' ?>>PHP 8.3</option>
                                </select>
                                <div class="help-text">Site için kullanılacak PHP versiyonu</div>
                            </div>
                            <div class="form-group">
                                <label>Site Durumu</label>
                                <select name="status" class="select-control">
                                    <option value="active" <?= ($_POST['status'] ?? 'active') == 'active' ? 'selected' : '' ?>>Aktif</option>
                                    <option value="inactive" <?= ($_POST['status'] ?? '') == 'inactive' ? 'selected' : '' ?>>Pasif</option>
                                    <option value="suspended" <?= ($_POST['status'] ?? '') == 'suspended' ? 'selected' : '' ?>>Askıya Alınmış</option>
                                </select>
                                <div class="help-text">Sitenin başlangıç durumu</div>
                            </div>
                        </div>
                    </div>

                    <!-- Güvenlik ve Yedekleme -->
                    <div class="form-section">
                        <h3>Güvenlik ve Yedekleme</h3>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" name="ssl_enabled" id="ssl_enabled" <?= isset($_POST['ssl_enabled']) ? 'checked' : 'checked' ?>>
                            <label for="ssl_enabled">SSL Sertifikası Aktif</label>
                        </div>
                        <div class="help-text">HTTPS bağlantısı için SSL sertifikası</div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" name="auto_backup" id="auto_backup" <?= isset($_POST['auto_backup']) ? 'checked' : 'checked' ?>>
                            <label for="auto_backup">Otomatik Yedekleme</label>
                        </div>
                        <div class="help-text">Günlük otomatik yedekleme sistemi</div>
                    </div>

                    <!-- Form Aksiyonları -->
                    <div class="form-actions">
                        <div>
                            <button type="submit" class="btn btn-success">Site Oluştur</button>
                            <a href="user-sites.php?id=<?= $user_id ?>" class="btn btn-primary">Site Listesi</a>
                        </div>
                        <div>
                            <a href="user-detail.php?id=<?= $user_id ?>" class="btn btn-secondary">Kullanıcı Detayı</a>
                        </div>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Site Oluşturma Kılavuzu</h2>
            <ol style="line-height: 1.6;">
                <li><strong>Domain Kontrolü:</strong> Domain adının size ait olduğundan emin olun</li>
                <li><strong>DNS Ayarları:</strong> Domain'in DNS kayıtlarını sunucunuza yönlendirin</li>
                <li><strong>SSL Sertifikası:</strong> Güvenlik için SSL'i aktif tutun</li>
                <li><strong>PHP Versiyonu:</strong> Sitenizin uyumlu olduğu PHP versiyonunu seçin</li>
                <li><strong>Yedekleme:</strong> Veri güvenliği için otomatik yedeklemeyi açın</li>
            </ol>
        </div>
    </div>

    <script>
    function updateDomainPreview() {
        const domain = document.getElementById('domain').value;
        const subdomain = document.getElementById('subdomain').value;
        const domainPreview = document.getElementById('domainPreview');
        const fullDomainPreview = document.getElementById('fullDomainPreview');
        const previewUrl = fullDomainPreview.querySelector('.preview-url');
        
        if (domain) {
            domainPreview.textContent = domain;
            
            const fullDomain = subdomain ? subdomain + '.' + domain : domain;
            previewUrl.textContent = 'https://' + fullDomain;
            fullDomainPreview.style.display = 'block';
        } else {
            domainPreview.textContent = 'domain.com';
            fullDomainPreview.style.display = 'none';
        }
    }
    
    // Event listeners
    document.getElementById('domain').addEventListener('input', updateDomainPreview);
    document.getElementById('subdomain').addEventListener('input', updateDomainPreview);
    
    // Form validation
    document.getElementById('siteForm').addEventListener('submit', function(e) {
        const domain = document.getElementById('domain').value.trim();
        
        if (!domain) {
            e.preventDefault();
            alert('Domain adı gerekli!');
            return false;
        }
        
        // Basic domain validation
        const domainRegex = /^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/;
        if (!domainRegex.test(domain)) {
            e.preventDefault();
            alert('Geçersiz domain formatı!');
            return false;
        }
        
        const subdomain = document.getElementById('subdomain').value.trim();
        const fullDomain = subdomain ? subdomain + '.' + domain : domain;
        
        return confirm('Bu siteyi oluşturmak istediğinizden emin misiniz?\n\nDomain: ' + fullDomain);
    });
    
    // Initialize preview
    updateDomainPreview();
    </script>
</body>
</html>