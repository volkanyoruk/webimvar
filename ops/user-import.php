<?php
// Toplu Kullanıcı İçe Aktarma Sayfası
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(300); // 5 dakika timeout

require_once '../core/classes/DatabaseConfig.php';

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

// Kullanılabilir sütunları kontrol et
$columns = [];
$column_result = $pdo->query("DESCRIBE users");
while($row = $column_result->fetch()) {
    $columns[] = $row['Field'];
}

// Paketleri getir
$packages = [];
try {
    $packages_query = "SELECT id, name FROM packages ORDER BY name ASC";
    $packages = $pdo->query($packages_query)->fetchAll();
} catch (Exception $e) {
    // Packages tablosu yok
}

$message = '';
$error = '';
$preview_data = [];
$import_results = [];

// CSV işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'preview' && isset($_FILES['csv_file'])) {
        // CSV Önizleme
        try {
            $file = $_FILES['csv_file'];
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Dosya yükleme hatası.");
            }
            
            if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
                throw new Exception("Dosya boyutu çok büyük (maksimum 5MB).");
            }
            
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($file_ext !== 'csv') {
                throw new Exception("Sadece CSV dosyaları kabul edilir.");
            }
            
            $temp_file = $file['tmp_name'];
            $csv_delimiter = $_POST['delimiter'] ?? ',';
            
            // CSV dosyasını oku
            $handle = fopen($temp_file, 'r');
            if (!$handle) {
                throw new Exception("Dosya okunamadı.");
            }
            
            // İlk satırı (başlık) al
            $headers = fgetcsv($handle, 0, $csv_delimiter);
            if (!$headers) {
                throw new Exception("CSV başlık satırı bulunamadı.");
            }
            
            // Maksimum 20 satır önizle
            $row_count = 0;
            while (($row = fgetcsv($handle, 0, $csv_delimiter)) !== false && $row_count < 20) {
                if (count($row) === count($headers)) {
                    $preview_data[] = array_combine($headers, $row);
                    $row_count++;
                }
            }
            
            fclose($handle);
            
            // Geçici dosyayı kaydet
            $temp_name = 'import_' . md5(session_id() . time()) . '.csv';
            $temp_path = sys_get_temp_dir() . '/' . $temp_name;
            move_uploaded_file($temp_file, $temp_path);
            $_SESSION['temp_csv'] = $temp_path;
            $_SESSION['csv_delimiter'] = $csv_delimiter;
            
            $message = count($preview_data) . " satır önizlendi. Devam etmek için aşağıdaki ayarları kontrol edin.";
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
        
    } elseif (isset($_POST['action']) && $_POST['action'] === 'import') {
        // Gerçek İçe Aktarma
        try {
            if (!isset($_SESSION['temp_csv']) || !file_exists($_SESSION['temp_csv'])) {
                throw new Exception("Önce dosya önizlemesi yapın.");
            }
            
            $temp_file = $_SESSION['temp_csv'];
            $csv_delimiter = $_SESSION['csv_delimiter'];
            $column_mapping = $_POST['column_mapping'] ?? [];
            $default_password = $_POST['default_password'] ?? '';
            $default_role = $_POST['default_role'] ?? 'user';
            $send_emails = isset($_POST['send_emails']);
            $skip_duplicates = isset($_POST['skip_duplicates']);
            
            if (empty($default_password)) {
                throw new Exception("Varsayılan şifre gerekli.");
            }
            
            $handle = fopen($temp_file, 'r');
            $headers = fgetcsv($handle, 0, $csv_delimiter);
            
            $pdo->beginTransaction();
            
            $success_count = 0;
            $error_count = 0;
            $skip_count = 0;
            $errors = [];
            
            while (($row = fgetcsv($handle, 0, $csv_delimiter)) !== false) {
                try {
                    if (count($row) !== count($headers)) {
                        continue;
                    }
                    
                    $data = array_combine($headers, $row);
                    $user_data = [];
                    
                    // Sütun eşleştirmelerini uygula
                    foreach ($column_mapping as $csv_column => $db_column) {
                        if (!empty($db_column) && isset($data[$csv_column]) && !empty($data[$csv_column])) {
                            $user_data[$db_column] = trim($data[$csv_column]);
                        }
                    }
                    
                    // Zorunlu alanları kontrol et
                    if (empty($user_data['username']) && empty($user_data['email'])) {
                        $errors[] = "Satır " . ($success_count + $error_count + 1) . ": Kullanıcı adı veya email gerekli.";
                        $error_count++;
                        continue;
                    }
                    
                    // Email yoksa username'den oluştur
                    if (empty($user_data['email']) && !empty($user_data['username'])) {
                        $user_data['email'] = $user_data['username'] . '@example.com';
                    }
                    
                    // Username yoksa email'den oluştur
                    if (empty($user_data['username']) && !empty($user_data['email'])) {
                        $user_data['username'] = explode('@', $user_data['email'])[0];
                    }
                    
                    // Duplikasyon kontrolü
                    if ($skip_duplicates) {
                        $dup_check = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
                        $dup_check->execute([$user_data['email'], $user_data['username']]);
                        if ($dup_check->fetch()) {
                            $skip_count++;
                            continue;
                        }
                    }
                    
                    // Insert verilerini hazırla
                    $insert_fields = [];
                    $insert_values = [];
                    $insert_placeholders = [];
                    
                    foreach ($user_data as $field => $value) {
                        if (in_array($field, $columns)) {
                            $insert_fields[] = $field;
                            $insert_values[] = $value;
                            $insert_placeholders[] = '?';
                        }
                    }
                    
                    // Varsayılan değerleri ekle
                    if (in_array('password', $columns)) {
                        $insert_fields[] = 'password';
                        $insert_values[] = password_hash($default_password, PASSWORD_DEFAULT);
                        $insert_placeholders[] = '?';
                    }
                    
                    if (in_array('role', $columns) && !isset($user_data['role'])) {
                        $insert_fields[] = 'role';
                        $insert_values[] = $default_role;
                        $insert_placeholders[] = '?';
                    }
                    
                    if (in_array('is_active', $columns)) {
                        $insert_fields[] = 'is_active';
                        $insert_values[] = 1;
                        $insert_placeholders[] = '?';
                    }
                    
                    if (in_array('created_at', $columns)) {
                        $insert_fields[] = 'created_at';
                        $insert_values[] = date('Y-m-d H:i:s');
                        $insert_placeholders[] = '?';
                    }
                    
                    // Insert yap
                    $sql = "INSERT INTO users (" . implode(', ', $insert_fields) . ") VALUES (" . implode(', ', $insert_placeholders) . ")";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($insert_values);
                    
                    $success_count++;
                    
                } catch (Exception $e) {
                    $errors[] = "Satır " . ($success_count + $error_count + 1) . ": " . $e->getMessage();
                    $error_count++;
                    
                    if ($error_count > 10) { // Maksimum 10 hata
                        break;
                    }
                }
            }
            
            fclose($handle);
            unlink($temp_file);
            unset($_SESSION['temp_csv']);
            unset($_SESSION['csv_delimiter']);
            
            $pdo->commit();
            
            $import_results = [
                'success' => $success_count,
                'errors' => $error_count,
                'skipped' => $skip_count,
                'error_messages' => $errors
            ];
            
            $message = "İçe aktarma tamamlandı: $success_count başarılı, $error_count hata, $skip_count atlandı.";
            
        } catch (Exception $e) {
            $pdo->rollback();
            $error = $e->getMessage();
        }
    }
}

// Sample CSV oluştur
if (isset($_GET['download_sample'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sample_users.csv"');
    
    $sample_data = [
        ['username', 'email', 'full_name', 'phone', 'role'],
        ['john_doe', 'john@example.com', 'John Doe', '+90 555 123 4567', 'user'],
        ['jane_smith', 'jane@example.com', 'Jane Smith', '+90 555 765 4321', 'user'],
        ['admin_user', 'admin@example.com', 'Admin User', '+90 555 999 0000', 'admin']
    ];
    
    $output = fopen('php://output', 'w');
    foreach ($sample_data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

session_start();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toplu Kullanıcı İçe Aktarma - Webimvar SaaS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f9fa; }
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { color: #2c3e50; margin-bottom: 5px; }
        .header p { color: #6c757d; }
        
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card h2 { color: #2c3e50; margin-bottom: 15px; font-size: 1.2rem; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #495057; }
        .form-control { width: 100%; padding: 10px 12px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 0.95rem; }
        .form-control:focus { outline: none; border-color: #007bff; box-shadow: 0 0 0 2px rgba(0,123,255,0.25); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.95rem; display: inline-block; margin: 5px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn:hover { opacity: 0.9; }
        
        .message { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        
        .table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .table th, .table td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #dee2e6; }
        .table th { background: #f8f9fa; font-weight: 600; }
        .table tr:hover { background: #f8f9fa; }
        
        .mapping-section { background: #f8f9fa; padding: 20px; border-radius: 4px; margin: 20px 0; }
        .mapping-row { display: grid; grid-template-columns: 1fr auto 1fr; gap: 15px; align-items: center; margin-bottom: 10px; }
        .mapping-arrow { text-align: center; color: #6c757d; font-weight: bold; }
        
        .preview-table { max-height: 400px; overflow: auto; border: 1px solid #dee2e6; border-radius: 4px; }
        
        .step { opacity: 0.5; pointer-events: none; }
        .step.active { opacity: 1; pointer-events: auto; }
        
        .progress { background: #e9ecef; height: 8px; border-radius: 4px; margin-bottom: 20px; }
        .progress-bar { background: #007bff; height: 100%; border-radius: 4px; transition: width 0.3s; }
        
        .checkbox-group { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
        .checkbox-group input[type="checkbox"] { transform: scale(1.2); }
        
        .help-text { font-size: 0.85rem; color: #6c757d; margin-top: 4px; }
        
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .mapping-row { grid-template-columns: 1fr; }
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
            <h1>Toplu Kullanıcı İçe Aktarma</h1>
            <p>CSV dosyasından toplu kullanıcı ekleme sistemi</p>
        </div>

        <!-- Progress Bar -->
        <div class="progress">
            <div class="progress-bar" style="width: <?= empty($preview_data) ? '25%' : (empty($import_results) ? '75%' : '100%') ?>"></div>
        </div>

        <?php if (empty($preview_data) && empty($import_results)): ?>
        <!-- Adım 1: Dosya Yükleme -->
        <div class="card step active">
            <h2>1. CSV Dosyası Yükle</h2>
            
            <div class="alert alert-info">
                <strong>Desteklenen Format:</strong> CSV dosyası (virgül veya noktalı virgül ayrımlı)<br>
                <strong>Maksimum Boyut:</strong> 5MB<br>
                <strong>Önerilen Sütunlar:</strong> username, email, full_name, phone, role<br>
                <a href="?download_sample=1" class="btn btn-secondary" style="margin-top: 10px;">Örnek CSV İndir</a>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="preview">
                
                <div class="form-group">
                    <label>CSV Dosyası Seç</label>
                    <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    <div class="help-text">CSV formatındaki kullanıcı listesini seçin</div>
                </div>
                
                <div class="form-group">
                    <label>CSV Ayırıcı</label>
                    <select name="delimiter" class="form-control">
                        <option value=",">Virgül (,)</option>
                        <option value=";">Noktalı Virgül (;)</option>
                        <option value="|">Pipe (|)</option>
                        <option value="\t">Tab</option>
                    </select>
                    <div class="help-text">CSV dosyasındaki sütun ayırıcı karakteri</div>
                </div>
                
                <button type="submit" class="btn btn-primary">Dosyayı Önizle</button>
                <a href="users.php" class="btn btn-secondary">İptal</a>
            </form>
        </div>
        <?php endif; ?>

        <?php if (!empty($preview_data) && empty($import_results)): ?>
        <!-- Adım 2: Sütun Eşleştirme ve Ayarlar -->
        <div class="card step active">
            <h2>2. Sütun Eşleştirme ve İçe Aktarma Ayarları</h2>
            
            <div class="preview-table">
                <table class="table">
                    <thead>
                        <tr>
                            <?php foreach (array_keys($preview_data[0]) as $header): ?>
                                <th><?= htmlspecialchars($header) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($preview_data, 0, 5) as $row): ?>
                            <tr>
                                <?php foreach ($row as $cell): ?>
                                    <td><?= htmlspecialchars($cell) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="import">
                
                <div class="mapping-section">
                    <h3>Sütun Eşleştirmeleri</h3>
                    <p class="help-text">CSV sütunlarını veritabanı alanlarıyla eşleştirin:</p>
                    
                    <?php foreach (array_keys($preview_data[0]) as $csv_column): ?>
                        <div class="mapping-row">
                            <div>
                                <strong><?= htmlspecialchars($csv_column) ?></strong>
                                <div class="help-text">CSV sütunu</div>
                            </div>
                            <div class="mapping-arrow">→</div>
                            <div>
                                <select name="column_mapping[<?= htmlspecialchars($csv_column) ?>]" class="form-control">
                                    <option value="">Eşleştirme</option>
                                    <?php if (in_array('username', $columns)): ?>
                                        <option value="username" <?= strtolower($csv_column) == 'username' ? 'selected' : '' ?>>Kullanıcı Adı</option>
                                    <?php endif; ?>
                                    <?php if (in_array('email', $columns)): ?>
                                        <option value="email" <?= strtolower($csv_column) == 'email' ? 'selected' : '' ?>>Email</option>
                                    <?php endif; ?>
                                    <?php if (in_array('full_name', $columns)): ?>
                                        <option value="full_name" <?= in_array(strtolower($csv_column), ['full_name', 'name', 'ad_soyad']) ? 'selected' : '' ?>>Tam İsim</option>
                                    <?php endif; ?>
                                    <?php if (in_array('phone', $columns)): ?>
                                        <option value="phone" <?= in_array(strtolower($csv_column), ['phone', 'telefon']) ? 'selected' : '' ?>>Telefon</option>
                                    <?php endif; ?>
                                    <?php if (in_array('role', $columns)): ?>
                                        <option value="role" <?= strtolower($csv_column) == 'role' ? 'selected' : '' ?>>Rol</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Varsayılan Şifre <span style="color: #dc3545;">*</span></label>
                        <input type="password" name="default_password" class="form-control" required>
                        <div class="help-text">Tüm kullanıcılar için aynı şifre</div>
                    </div>
                    <div class="form-group">
                        <label>Varsayılan Rol</label>
                        <select name="default_role" class="form-control">
                            <option value="user">Kullanıcı</option>
                            <option value="admin">Yönetici</option>
                            <option value="manager">Müdür</option>
                            <option value="support">Destek</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="skip_duplicates" id="skip_duplicates" checked>
                        <label for="skip_duplicates">Mevcut kullanıcıları atla</label>
                    </div>
                    <div class="help-text">Aynı email/kullanıcı adına sahip kayıtları atla</div>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="send_emails" id="send_emails">
                        <label for="send_emails">Hoşgeldin emaili gönder</label>
                    </div>
                    <div class="help-text">Yeni kullanıcılara hesap bilgilerini emaille gönder</div>
                </div>
                
                <div style="margin-top: 30px;">
                    <button type="submit" class="btn btn-success" onclick="return confirm('<?= count($preview_data) ?> kullanıcıyı içe aktarmak istediğinizden emin misiniz?')">İçe Aktarmayı Başlat</button>
                    <a href="user-import.php" class="btn btn-secondary">Yeni Dosya Yükle</a>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <?php if (!empty($import_results)): ?>
        <!-- Adım 3: Sonuçlar -->
        <div class="card step active">
            <h2>3. İçe Aktarma Sonuçları</h2>
            
            <div class="form-row">
                <div class="alert alert-info">
                    <strong>Başarılı:</strong> <?= $import_results['success'] ?> kullanıcı<br>
                    <strong>Hata:</strong> <?= $import_results['errors'] ?> kullanıcı<br>
                    <strong>Atlanan:</strong> <?= $import_results['skipped'] ?> kullanıcı
                </div>
            </div>
            
            <?php if (!empty($import_results['error_messages'])): ?>
                <div class="alert alert-warning">
                    <strong>Hatalar:</strong>
                    <ul style="margin: 10px 0 0 20px;">
                        <?php foreach (array_slice($import_results['error_messages'], 0, 10) as $error_msg): ?>
                            <li><?= htmlspecialchars($error_msg) ?></li>
                        <?php endforeach; ?>
                        <?php if (count($import_results['error_messages']) > 10): ?>
                            <li><em>... ve <?= count($import_results['error_messages']) - 10 ?> hata daha</em></li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 30px;">
                <a href="users.php" class="btn btn-primary">Kullanıcı Listesine Git</a>
                <a href="user-import.php" class="btn btn-success">Yeni İçe Aktarma</a>
                <a href="user-add.php" class="btn btn-secondary">Tek Kullanıcı Ekle</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Yardım -->
        <div class="card">
            <h2>Kullanım Kılavuzu</h2>
            <ol>
                <li><strong>CSV Hazırlama:</strong> Excel'de verilerinizi hazırlayın ve "CSV (virgülle ayrılmış)" formatında kaydedin.</li>
                <li><strong>Sütun Başlıkları:</strong> İlk satırda sütun başlıklarını kullanın (username, email, full_name vs.)</li>
                <li><strong>Veri Kalitesi:</strong> Email adreslerinin doğru formatda olduğundan emin olun.</li>
                <li><strong>Test:</strong> Büyük dosyalar öncesi küçük bir örnekle test yapın.</li>
                <li><strong>Yedekleme:</strong> İçe aktarma öncesi mevcut verilerinizi yedekleyin.</li>
            </ol>
        </div>
    </div>
</body>
</html>