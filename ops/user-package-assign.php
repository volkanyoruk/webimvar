<?php
// Kullanıcı Paket Atama
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

// Mevcut paket bilgisi
$current_package = null;
if (isset($user['package_id']) && $user['package_id']) {
    try {
        $package_query = "SELECT * FROM packages WHERE id = ?";
        $package_stmt = $pdo->prepare($package_query);
        $package_stmt->execute([$user['package_id']]);
        $current_package = $package_stmt->fetch();
    } catch (Exception $e) {
        // Packages tablosu yok
    }
}

// Tüm paketleri getir
$packages = [];
try {
    $packages_query = "SELECT * FROM packages ORDER BY price ASC";
    $packages = $pdo->query($packages_query)->fetchAll();
} catch (Exception $e) {
    // Packages tablosu yok
}

// Kullanıcının site sayısını kontrol et
$user_sites_count = 0;
try {
    $sites_count_query = "SELECT COUNT(*) FROM sites WHERE user_id = ?";
    $sites_stmt = $pdo->prepare($sites_count_query);
    $sites_stmt->execute([$user_id]);
    $user_sites_count = $sites_stmt->fetchColumn();
} catch (Exception $e) {
    // Sites tablosu yok
}

$message = '';
$error = '';

// Paket atama işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $new_package_id = (int)($_POST['package_id'] ?? 0);
    $send_notification = isset($_POST['send_notification']);
    $prorate_billing = isset($_POST['prorate_billing']);
    $notes = $_POST['notes'] ?? '';
    
    try {
        $columns = [];
        $column_result = $pdo->query("DESCRIBE users");
        while($row = $column_result->fetch()) {
            $columns[] = $row['Field'];
        }
        
        if ($action === 'assign' && $new_package_id > 0) {
            // Yeni paket ata
            $new_package = null;
            foreach ($packages as $pkg) {
                if ($pkg['id'] == $new_package_id) {
                    $new_package = $pkg;
                    break;
                }
            }
            
            if (!$new_package) {
                throw new Exception("Seçilen paket bulunamadı.");
            }
            
            // Site limitini kontrol et
            if (isset($new_package['sites_limit']) && $new_package['sites_limit'] > 0 && $user_sites_count > $new_package['sites_limit']) {
                throw new Exception("Kullanıcının {$user_sites_count} sitesi var, ancak seçilen paket maksimum {$new_package['sites_limit']} site destekliyor.");
            }
            
            $pdo->beginTransaction();
            
            // Kullanıcının paketini güncelle
            if (in_array('package_id', $columns)) {
                $update_fields = ["package_id = ?"];
                $update_values = [$new_package_id];
                
                if (in_array('updated_at', $columns)) {
                    $update_fields[] = "updated_at = NOW()";
                }
                
                $update_values[] = $user_id;
                $update_sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute($update_values);
            }
            
            // Paket değişiklik geçmişi kaydet (eğer system_logs tablosu varsa)
            try {
                $old_package_name = $current_package['name'] ?? 'Paket yok';
                $new_package_name = $new_package['name'];
                
                $log_query = "INSERT INTO system_logs (user_id, action, details, ip_address, created_at) VALUES (?, 'package_changed', ?, ?, NOW())";
                $log_stmt = $pdo->prepare($log_query);
                $log_stmt->execute([
                    $user_id,
                    "Package changed from '{$old_package_name}' to '{$new_package_name}' by admin. Notes: {$notes}",
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            } catch (Exception $e) {
                // Log tablosu yoksa sessizce devam et
            }
            
            // Faturalama kaydı oluştur (eğer tablolar varsa)
            if ($prorate_billing) {
                try {
                    // Basit faturalama - gerçek uygulamada daha karmaşık hesaplama gerekir
                    $invoice_query = "INSERT INTO invoices (user_id, amount, description, status, due_date, created_at) VALUES (?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())";
                    $invoice_stmt = $pdo->prepare($invoice_query);
                    $invoice_stmt->execute([
                        $user_id,
                        $new_package['price'],
                        "Paket değişikliği: {$new_package['name']}"
                    ]);
                } catch (Exception $e) {
                    // Invoices tablosu yoksa sessizce devam et
                }
            }
            
            $pdo->commit();
            $message = "Paket başarıyla '{$new_package['name']}' olarak değiştirildi.";
            
            // Mevcut paket bilgisini güncelle
            $current_package = $new_package;
            $user['package_id'] = $new_package_id;
            
        } elseif ($action === 'remove') {
            // Paketi kaldır
            if (in_array('package_id', $columns)) {
                $pdo->beginTransaction();
                
                $update_query = "UPDATE users SET package_id = NULL";
                if (in_array('updated_at', $columns)) {
                    $update_query .= ", updated_at = NOW()";
                }
                $update_query .= " WHERE id = ?";
                
                $pdo->prepare($update_query)->execute([$user_id]);
                
                // Log kaydı
                try {
                    $old_package_name = $current_package['name'] ?? 'Bilinmeyen paket';
                    $log_query = "INSERT INTO system_logs (user_id, action, details, ip_address, created_at) VALUES (?, 'package_removed', ?, ?, NOW())";
                    $log_stmt = $pdo->prepare($log_query);
                    $log_stmt->execute([
                        $user_id,
                        "Package '{$old_package_name}' removed by admin. Notes: {$notes}",
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);
                } catch (Exception $e) {
                    // Log tablosu yoksa sessizce devam et
                }
                
                $pdo->commit();
                $message = "Paket başarıyla kaldırıldı.";
                
                $current_package = null;
                $user['package_id'] = null;
            }
        } else {
            throw new Exception("Geçersiz işlem.");
        }
        
        if ($send_notification) {
            // TODO: Email bildirim gönder
            $message .= " Kullanıcıya bildirim gönderildi.";
        }
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        $error = $e->getMessage();
    }
}

// Paket geçmişi
$package_history = [];
try {
    $history_query = "SELECT * FROM system_logs WHERE user_id = ? AND action IN ('package_changed', 'package_removed', 'package_assigned') ORDER BY created_at DESC LIMIT 10";
    $history_stmt = $pdo->prepare($history_query);
    $history_stmt->execute([$user_id]);
    $package_history = $history_stmt->fetchAll();
} catch (Exception $e) {
    // System_logs tablosu yok
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paket Atama - <?= htmlspecialchars($user['username'] ?? $user['email']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f9fa; }
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { color: #2c3e50; margin-bottom: 5px; }
        .header p { color: #6c757d; }
        
        .user-info { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 20px; }
        .user-avatar { width: 60px; height: 60px; border-radius: 50%; background: #007bff; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; }
        .user-details h3 { color: #2c3e50; margin-bottom: 5px; }
        .user-details p { color: #6c757d; margin: 2px 0; }
        
        .current-package { background: white; padding: 25px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .current-package h2 { color: #2c3e50; margin-bottom: 15px; }
        
        .package-card { border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; background: #f8f9fa; }
        .package-card.active { border-color: #007bff; background: #e7f3ff; }
        .package-name { font-size: 1.2rem; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
        .package-price { font-size: 1.5rem; font-weight: bold; color: #28a745; margin-bottom: 15px; }
        .package-features { list-style: none; margin-bottom: 15px; }
        .package-features li { padding: 5px 0; color: #6c757d; }
        .package-features li:before { content: "✓ "; color: #28a745; font-weight: bold; }
        
        .packages-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0; }
        .package-option { border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; background: white; cursor: pointer; transition: all 0.3s; }
        .package-option:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .package-option.selected { border-color: #007bff; background: #e7f3ff; }
        .package-option input[type="radio"] { margin-bottom: 15px; }
        
        .form-section { background: white; padding: 25px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .form-section h3 { color: #2c3e50; margin-bottom: 20px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #495057; }
        .form-control { width: 100%; padding: 10px 12px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 0.95rem; }
        .form-control:focus { outline: none; border-color: #007bff; box-shadow: 0 0 0 2px rgba(0,123,255,0.25); }
        
        .checkbox-group { display: flex; align-items: center; gap: 8px; margin: 15px 0; }
        .checkbox-group input[type="checkbox"] { transform: scale(1.2); }
        
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.95rem; display: inline-block; margin: 5px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn:hover { opacity: 0.9; }
        .btn-large { padding: 15px 30px; font-size: 1.1rem; }
        
        .message { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        
        .comparison-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .comparison-table th, .comparison-table td { padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; }
        .comparison-table th { background: #f8f9fa; font-weight: 600; }
        .comparison-table .current { background: #e7f3ff; }
        .comparison-table .new { background: #e8f5e8; }
        
        .history-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .history-table th, .history-table td { padding: 10px; text-align: left; border-bottom: 1px solid #dee2e6; }
        .history-table th { background: #f8f9fa; font-weight: 600; }
        .history-table tr:hover { background: #f8f9fa; }
        
        .help-text { font-size: 0.85rem; color: #6c757d; margin-top: 4px; }
        .limitations { background: #fff3cd; border: 1px solid #ffeeba; padding: 15px; border-radius: 4px; margin: 15px 0; }
        
        @media (max-width: 768px) {
            .user-info { flex-direction: column; text-align: center; }
            .packages-grid { grid-template-columns: 1fr; }
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
            <h1>Paket Atama</h1>
            <p>Kullanıcıya hosting paketi atayın veya mevcut paketi değiştirin</p>
        </div>

        <div class="user-info">
            <div class="user-avatar">
                <?= strtoupper(substr($user['username'] ?? $user['email'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="user-details">
                <h3><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'Kullanıcı') ?></h3>
                <p><strong>Email:</strong> <?= htmlspecialchars($user['email'] ?? 'N/A') ?></p>
                <p><strong>Mevcut site sayısı:</strong> <?= $user_sites_count ?></p>
                <p><strong>Durum:</strong> <?= isset($user['is_active']) && $user['is_active'] ? 'Aktif' : 'Pasif' ?></p>
            </div>
        </div>

        <!-- Mevcut Paket -->
        <div class="current-package">
            <h2>Mevcut Paket</h2>
            <?php if ($current_package): ?>
                <div class="package-card active">
                    <div class="package-name"><?= htmlspecialchars($current_package['name']) ?></div>
                    <div class="package-price"><?= number_format($current_package['price']) ?> ₺/ay</div>
                    <ul class="package-features">
                        <?php if ($current_package['disk_space'] ?? false): ?>
                            <li><?= $current_package['disk_space'] ?> GB Disk Alanı</li>
                        <?php endif; ?>
                        <?php if ($current_package['bandwidth'] ?? false): ?>
                            <li><?= $current_package['bandwidth'] ?> GB Bant Genişliği</li>
                        <?php endif; ?>
                        <?php if ($current_package['sites_limit'] ?? false): ?>
                            <li><?= $current_package['sites_limit'] ?> Site Hakkı</li>
                        <?php endif; ?>
                        <?php if ($current_package['email_accounts'] ?? false): ?>
                            <li><?= $current_package['email_accounts'] ?> Email Hesabı</li>
                        <?php endif; ?>
                        <?php if ($current_package['databases'] ?? false): ?>
                            <li><?= $current_package['databases'] ?> Veritabanı</li>
                        <?php endif; ?>
                    </ul>
                    
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="remove">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Bu paketi kaldırmak istediğinizden emin misiniz?')">
                            Paketi Kaldır
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    Bu kullanıcının henüz atanmış bir paketi bulunmuyor.
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($packages)): ?>
            <div class="alert alert-warning">
                <strong>Uyarı:</strong> Packages tablosu bulunamadı veya hiç paket tanımlanmamış. 
                Paket atayabilmek için önce paketleri tanımlamanız gerekir.
                <br><br>
                <a href="packages.php" class="btn btn-primary">Paket Yönetimi</a>
            </div>
        <?php else: ?>
            <!-- Yeni Paket Seç -->
            <form method="POST" id="packageForm">
                <input type="hidden" name="action" value="assign">
                
                <div class="form-section">
                    <h3>Yeni Paket Seç</h3>
                    
                    <div class="packages-grid">
                        <?php foreach ($packages as $package): 
                            $is_current = $current_package && $current_package['id'] == $package['id'];
                            $is_suitable = true;
                            
                            // Site limitini kontrol et
                            if (isset($package['sites_limit']) && $package['sites_limit'] > 0 && $user_sites_count > $package['sites_limit']) {
                                $is_suitable = false;
                            }
                        ?>
                            <div class="package-option <?= $is_current ? 'selected' : '' ?>" onclick="selectPackage(<?= $package['id'] ?>, this)" data-package-id="<?= $package['id'] ?>">
                                <input type="radio" name="package_id" value="<?= $package['id'] ?>" id="package_<?= $package['id'] ?>" <?= $is_current ? 'checked disabled' : '' ?>>
                                
                                <div class="package-name">
                                    <?= htmlspecialchars($package['name']) ?>
                                    <?php if ($is_current): ?>
                                        <span style="color: #007bff; font-size: 0.8rem;">(Mevcut)</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="package-price"><?= number_format($package['price']) ?> ₺/ay</div>
                                
                                <ul class="package-features">
                                    <?php if ($package['disk_space'] ?? false): ?>
                                        <li><?= $package['disk_space'] ?> GB Disk Alanı</li>
                                    <?php endif; ?>
                                    <?php if ($package['bandwidth'] ?? false): ?>
                                        <li><?= $package['bandwidth'] ?> GB Bant Genişliği</li>
                                    <?php endif; ?>
                                    <?php if ($package['sites_limit'] ?? false): ?>
                                        <li>
                                            <?= $package['sites_limit'] ?> Site Hakkı
                                            <?php if (!$is_suitable): ?>
                                                <span style="color: #dc3545; font-size: 0.8rem;">(Yetersiz)</span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endif; ?>
                                    <?php if ($package['email_accounts'] ?? false): ?>
                                        <li><?= $package['email_accounts'] ?> Email Hesabı</li>
                                    <?php endif; ?>
                                    <?php if ($package['databases'] ?? false): ?>
                                        <li><?= $package['databases'] ?> Veritabanı</li>
                                    <?php endif; ?>
                                </ul>
                                
                                <?php if (!$is_suitable): ?>
                                    <div class="limitations">
                                        <strong>Uyarı:</strong> Kullanıcının <?= $user_sites_count ?> sitesi var, 
                                        bu paket maksimum <?= $package['sites_limit'] ?> site destekliyor.
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Atama Ayarları</h3>
                    
                    <div class="form-group">
                        <label>Admin Notları</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Bu paket değişikliği hakkında notlarınız..."></textarea>
                        <div class="help-text">Bu notlar sistem loglarında saklanacak</div>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" name="send_notification" id="send_notification" checked>
                        <label for="send_notification">Kullanıcıya email bildirimi gönder</label>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" name="prorate_billing" id="prorate_billing">
                        <label for="prorate_billing">Aylık faturalama oluştur</label>
                    </div>
                    <div class="help-text">Bu seçenek yeni paket için fatura oluşturacak</div>
                </div>

                <div style="text-align: center; margin: 30px 0;">
                    <button type="submit" class="btn btn-success btn-large" onclick="return confirm('Paketi değiştirmek istediğinizden emin misiniz?')">
                        Paketi Ata
                    </button>
                    <a href="user-detail.php?id=<?= $user_id ?>" class="btn btn-secondary btn-large">İptal Et</a>
                </div>
            </form>
        <?php endif; ?>

        <!-- Paket Karşılaştırma -->
        <?php if ($current_package && !empty($packages)): ?>
        <div class="form-section">
            <h3>Paket Karşılaştırması</h3>
            <p>Seçilen yeni paket ile mevcut paket arasındaki farkları görün:</p>
            
            <div id="comparisonSection" style="display: none;">
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Özellik</th>
                            <th class="current">Mevcut Paket</th>
                            <th class="new">Yeni Paket</th>
                        </tr>
                    </thead>
                    <tbody id="comparisonBody">
                        <!-- JavaScript ile doldurulacak -->
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Paket Geçmişi -->
        <?php if (!empty($package_history)): ?>
        <div class="form-section">
            <h3>Paket Değişiklik Geçmişi</h3>
            
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
                    <?php foreach ($package_history as $log): ?>
                        <tr>
                            <td><?= ucfirst(str_replace('_', ' ', $log['action'])) ?></td>
                            <td><?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?></td>
                            <td><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($log['details'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="user-detail.php?id=<?= $user_id ?>" class="btn btn-primary">Kullanıcı Detayları</a>
            <a href="user-edit.php?id=<?= $user_id ?>" class="btn btn-secondary">Kullanıcıyı Düzenle</a>
            <a href="users.php" class="btn btn-secondary">Kullanıcı Listesi</a>
        </div>
    </div>

    <script>
    const packages = <?= json_encode($packages) ?>;
    const currentPackage = <?= json_encode($current_package) ?>;
    
    function selectPackage(packageId, element) {
        // Önceki seçimi kaldır
        document.querySelectorAll('.package-option').forEach(el => el.classList.remove('selected'));
        
        // Yeni seçimi işaretle
        element.classList.add('selected');
        
        // Radio button'ı seç
        document.getElementById('package_' + packageId).checked = true;
        
        // Karşılaştırma tablosunu güncelle
        updateComparison(packageId);
    }
    
    function updateComparison(newPackageId) {
        if (!currentPackage || !newPackageId) return;
        
        const newPackage = packages.find(p => p.id == newPackageId);
        if (!newPackage) return;
        
        const comparisonSection = document.getElementById('comparisonSection');
        const comparisonBody = document.getElementById('comparisonBody');
        
        const features = [
            {key: 'price', label: 'Aylık Ücret', format: (val) => val + ' ₺'},
            {key: 'disk_space', label: 'Disk Alanı', format: (val) => val ? val + ' GB' : 'Belirtilmemiş'},
            {key: 'bandwidth', label: 'Bant Genişliği', format: (val) => val ? val + ' GB' : 'Belirtilmemiş'},
            {key: 'sites_limit', label: 'Site Limitı', format: (val) => val || 'Sınırsız'},
            {key: 'email_accounts', label: 'Email Hesapları', format: (val) => val || 'Belirtilmemiş'},
            {key: 'databases', label: 'Veritabanları', format: (val) => val || 'Belirtilmemiş'}
        ];
        
        let html = '';
        features.forEach(feature => {
            const currentVal = currentPackage[feature.key] || null;
            const newVal = newPackage[feature.key] || null;
            const currentFormatted = feature.format(currentVal);
            const newFormatted = feature.format(newVal);
            
            // Değişiklik durumunu belirle
            let changeClass = '';
            if (feature.key === 'price') {
                if (newVal > currentVal) changeClass = 'style="color: #dc3545;"'; // Artış kırmızı
                else if (newVal < currentVal) changeClass = 'style="color: #28a745;"'; // Azalış yeşil
            } else {
                if (newVal > currentVal) changeClass = 'style="color: #28a745;"'; // Artış yeşil (özellikler için)
                else if (newVal < currentVal) changeClass = 'style="color: #dc3545;"'; // Azalış kırmızı
            }
            
            html += `
                <tr>
                    <td>${feature.label}</td>
                    <td class="current">${currentFormatted}</td>
                    <td class="new" ${changeClass}>${newFormatted}</td>
                </tr>
            `;
        });
        
        comparisonBody.innerHTML = html;
        comparisonSection.style.display = 'block';
    }
    
    // Form submit kontrolü
    document.getElementById('packageForm').addEventListener('submit', function(e) {
        const selectedPackage = document.querySelector('input[name="package_id"]:checked');
        if (!selectedPackage) {
            e.preventDefault();
            alert('Lütfen bir paket seçin!');
            return false;
        }
        
        const selectedId = selectedPackage.value;
        const packageName = packages.find(p => p.id == selectedId)?.name;
        
        return confirm(`"${packageName}" paketini atamak istediğinizden emin misiniz?`);
    });
    </script>
</body>
</html>