<?php
// Kullanıcı Dışa Aktarma (CSV Export)
ini_set('display_errors', 1);
error_reporting(E_ALL);

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

// Seçilen kullanıcı ID'leri (eğer belirtilmişse)
$selected_ids = [];
if (isset($_GET['ids'])) {
    $selected_ids = array_filter(array_map('intval', explode(',', $_GET['ids'])));
}

// Export türü belirleme
$export_type = $_GET['type'] ?? 'selected';

// Direkt export yapılacak mı?
if (isset($_GET['download']) && $_GET['download'] === '1') {
    $export_fields = $_GET['export_fields'] ?? [];
    $export_format = $_GET['format'] ?? 'csv';
    $include_headers = isset($_GET['include_headers']);
    $date_format = $_GET['date_format'] ?? 'd.m.Y H:i';
    
    if (empty($export_fields)) {
        die("Dışa aktarılacak alanları seçin.");
    }
    
    // Sorgu oluştur
    $where_clause = "";
    $params = [];
    
    if ($export_type === 'selected' && !empty($selected_ids)) {
        $placeholders = str_repeat('?,', count($selected_ids) - 1) . '?';
        $where_clause = "WHERE id IN ($placeholders)";
        $params = $selected_ids;
    } elseif ($export_type === 'filtered') {
        // Filtreleme parametrelerini al
        $role_filter = $_GET['role_filter'] ?? '';
        $status_filter = $_GET['status_filter'] ?? '';
        $date_from = $_GET['date_from'] ?? '';
        $date_to = $_GET['date_to'] ?? '';
        
        $conditions = [];
        
        if ($role_filter && in_array('role', $columns)) {
            $conditions[] = "role = ?";
            $params[] = $role_filter;
        }
        
        if ($status_filter && in_array('is_active', $columns)) {
            $status_value = ($status_filter === 'active') ? 1 : 0;
            $conditions[] = "is_active = ?";
            $params[] = $status_value;
        }
        
        if ($date_from && in_array('created_at', $columns)) {
            $conditions[] = "created_at >= ?";
            $params[] = $date_from . ' 00:00:00';
        }
        
        if ($date_to && in_array('created_at', $columns)) {
            $conditions[] = "created_at <= ?";
            $params[] = $date_to . ' 23:59:59';
        }
        
        if (!empty($conditions)) {
            $where_clause = "WHERE " . implode(' AND ', $conditions);
        }
    }
    
    // Sadece mevcut alanları kullan
    $valid_fields = array_intersect($export_fields, $columns);
    
    if (empty($valid_fields)) {
        die("Geçerli export alanı bulunamadı.");
    }
    
    $sql = "SELECT " . implode(', ', $valid_fields) . " FROM users $where_clause ORDER BY id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($export_format === 'csv') {
        // CSV Export
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d_H-i') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // BOM ekle (Türkçe karakter desteği için)
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Başlıkları yaz
        if ($include_headers) {
            $headers = [];
            foreach ($valid_fields as $field) {
                $headers[] = ucfirst(str_replace('_', ' ', $field));
            }
            fputcsv($output, $headers, ';'); // Noktalı virgül kullan (Excel için)
        }
        
        // Veri satırları
        foreach ($users as $user) {
            $row = [];
            foreach ($valid_fields as $field) {
                $value = $user[$field] ?? '';
                
                // Tarih formatlaması
                if (in_array($field, ['created_at', 'updated_at', 'last_login']) && $value && $value !== '0000-00-00 00:00:00') {
                    $value = date($date_format, strtotime($value));
                }
                
                // Boolean değerleri
                if ($field === 'is_active') {
                    $value = $value ? 'Aktif' : 'Pasif';
                }
                
                $row[] = $value;
            }
            fputcsv($output, $row, ';');
        }
        
        fclose($output);
        exit;
        
    } elseif ($export_format === 'json') {
        // JSON Export
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d_H-i') . '.json"');
        
        // Tarihleri formatla
        foreach ($users as &$user) {
            foreach (['created_at', 'updated_at', 'last_login'] as $date_field) {
                if (isset($user[$date_field]) && $user[$date_field] && $user[$date_field] !== '0000-00-00 00:00:00') {
                    $user[$date_field] = date($date_format, strtotime($user[$date_field]));
                }
            }
            
            // Boolean değerleri
            if (isset($user['is_active'])) {
                $user['is_active'] = $user['is_active'] ? 'Aktif' : 'Pasif';
            }
        }
        
        echo json_encode([
            'export_date' => date('Y-m-d H:i:s'),
            'total_records' => count($users),
            'fields' => $valid_fields,
            'data' => $users
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

// Paket bilgileri (eğer varsa)
$packages = [];
try {
    $packages_query = "SELECT id, name FROM packages ORDER BY name ASC";
    $packages = $pdo->query($packages_query)->fetchAll();
} catch (Exception $e) {
    // Packages tablosu yok
}

// Roller listesi
$roles = [];
if (in_array('role', $columns)) {
    $role_result = $pdo->query("SELECT DISTINCT role FROM users WHERE role IS NOT NULL ORDER BY role");
    $roles = $role_result->fetchAll(PDO::FETCH_COLUMN);
}

// İstatistikler
$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'selected_count' => count($selected_ids)
];

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Dışa Aktarma - Webimvar SaaS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f9fa; }
        .container { max-width: 900px; margin: 0 auto; padding: 20px; }
        
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { color: #2c3e50; margin-bottom: 5px; }
        .header p { color: #6c757d; }
        
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card h2 { color: #2c3e50; margin-bottom: 15px; font-size: 1.2rem; }
        
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
        .stat-number { font-size: 2rem; font-weight: bold; color: #007bff; }
        .stat-label { color: #6c757d; font-size: 0.9rem; margin-top: 5px; }
        
        .form-section { margin-bottom: 25px; }
        .form-section h3 { color: #495057; margin-bottom: 15px; font-size: 1rem; border-bottom: 1px solid #e9ecef; padding-bottom: 8px; }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px; }
        .form-row.single { grid-template-columns: 1fr; }
        
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 8px; font-weight: 500; color: #495057; }
        .form-control { padding: 10px 12px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 0.95rem; }
        .form-control:focus { outline: none; border-color: #007bff; box-shadow: 0 0 0 2px rgba(0,123,255,0.25); }
        
        .checkbox-group { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
        .checkbox-item { display: flex; align-items: center; gap: 8px; padding: 8px; }
        .checkbox-item input[type="checkbox"] { transform: scale(1.1); }
        
        .radio-group { display: flex; gap: 20px; flex-wrap: wrap; }
        .radio-item { display: flex; align-items: center; gap: 8px; }
        .radio-item input[type="radio"] { transform: scale(1.1); }
        
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.95rem; display: inline-block; margin: 5px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn:hover { opacity: 0.9; }
        .btn-large { padding: 15px 30px; font-size: 1.1rem; }
        
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        
        .preview-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .preview-table { max-height: 300px; overflow: auto; border: 1px solid #dee2e6; border-radius: 4px; }
        
        .help-text { font-size: 0.85rem; color: #6c757d; margin-top: 4px; }
        
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .radio-group { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Kullanıcı Dışa Aktarma</h1>
            <p>Kullanıcı verilerini CSV veya JSON formatında dışa aktarın</p>
        </div>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['total_users']) ?></div>
                <div class="stat-label">Toplam Kullanıcı</div>
            </div>
            <?php if ($export_type === 'selected'): ?>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($stats['selected_count']) ?></div>
                    <div class="stat-label">Seçilen Kullanıcı</div>
                </div>
            <?php endif; ?>
        </div>

        <form method="GET" id="exportForm">
            <input type="hidden" name="download" value="1">
            <?php if (!empty($selected_ids)): ?>
                <input type="hidden" name="ids" value="<?= implode(',', $selected_ids) ?>">
            <?php endif; ?>
            
            <div class="card">
                <h2>Export Ayarları</h2>
                
                <!-- Export Türü -->
                <div class="form-section">
                    <h3>Export Türü</h3>
                    <div class="radio-group">
                        <div class="radio-item">
                            <input type="radio" name="type" value="all" id="type_all" <?= $export_type === 'all' ? 'checked' : '' ?>>
                            <label for="type_all">Tüm Kullanıcılar</label>
                        </div>
                        <?php if (!empty($selected_ids)): ?>
                        <div class="radio-item">
                            <input type="radio" name="type" value="selected" id="type_selected" <?= $export_type === 'selected' ? 'checked' : '' ?>>
                            <label for="type_selected">Seçilen Kullanıcılar (<?= count($selected_ids) ?>)</label>
                        </div>
                        <?php endif; ?>
                        <div class="radio-item">
                            <input type="radio" name="type" value="filtered" id="type_filtered" <?= $export_type === 'filtered' ? 'checked' : '' ?>>
                            <label for="type_filtered">Filtreli Export</label>
                        </div>
                    </div>
                </div>

                <!-- Filtreleme Seçenekleri -->
                <div class="form-section" id="filterSection" style="<?= $export_type !== 'filtered' ? 'display: none;' : '' ?>">
                    <h3>Filtreleme Seçenekleri</h3>
                    
                    <div class="form-row">
                        <?php if (!empty($roles)): ?>
                        <div class="form-group">
                            <label>Rol</label>
                            <select name="role_filter" class="form-control">
                                <option value="">Tüm Roller</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= htmlspecialchars($role) ?>"><?= ucfirst($role) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (in_array('is_active', $columns)): ?>
                        <div class="form-group">
                            <label>Durum</label>
                            <select name="status_filter" class="form-control">
                                <option value="">Tüm Durumlar</option>
                                <option value="active">Aktif</option>
                                <option value="inactive">Pasif</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (in_array('created_at', $columns)): ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Başlangıç Tarihi</label>
                            <input type="date" name="date_from" class="form-control">
                            <div class="help-text">Bu tarihten sonra kayıt olanlar</div>
                        </div>
                        <div class="form-group">
                            <label>Bitiş Tarihi</label>
                            <input type="date" name="date_to" class="form-control">
                            <div class="help-text">Bu tarihten önce kayıt olanlar</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Export Alanları -->
                <div class="form-section">
                    <h3>Dışa Aktarılacak Alanlar</h3>
                    <div class="checkbox-group">
                        <?php
                        $field_labels = [
                            'id' => 'Kullanıcı ID',
                            'username' => 'Kullanıcı Adı',
                            'email' => 'Email',
                            'full_name' => 'Tam İsim',
                            'phone' => 'Telefon',
                            'role' => 'Rol',
                            'is_active' => 'Durum',
                            'created_at' => 'Kayıt Tarihi',
                            'updated_at' => 'Güncellenme Tarihi',
                            'last_login' => 'Son Giriş'
                        ];
                        
                        foreach ($columns as $column):
                            if (in_array($column, ['password', 'remember_token'])) continue; // Hassas alanları atla
                            $label = $field_labels[$column] ?? ucfirst(str_replace('_', ' ', $column));
                        ?>
                            <div class="checkbox-item">
                                <input type="checkbox" name="export_fields[]" value="<?= $column ?>" id="field_<?= $column ?>" <?= in_array($column, ['id', 'username', 'email']) ? 'checked' : '' ?>>
                                <label for="field_<?= $column ?>"><?= $label ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <button type="button" onclick="selectAllFields()" class="btn btn-secondary">Tümünü Seç</button>
                        <button type="button" onclick="selectNoFields()" class="btn btn-secondary">Hiçbirini Seçme</button>
                        <button type="button" onclick="selectBasicFields()" class="btn btn-secondary">Temel Alanlar</button>
                    </div>
                </div>

                <!-- Format ve Seçenekler -->
                <div class="form-section">
                    <h3>Format ve Seçenekler</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Export Formatı</label>
                            <select name="format" class="form-control">
                                <option value="csv">CSV (Comma Separated Values)</option>
                                <option value="json">JSON (JavaScript Object Notation)</option>
                            </select>
                            <div class="help-text">CSV Excel'de açılabilir, JSON programatik kullanım için</div>
                        </div>
                        <div class="form-group">
                            <label>Tarih Formatı</label>
                            <select name="date_format" class="form-control">
                                <option value="d.m.Y H:i">31.12.2023 14:30</option>
                                <option value="Y-m-d H:i:s">2023-12-31 14:30:00</option>
                                <option value="d/m/Y">31/12/2023</option>
                                <option value="Y-m-d">2023-12-31</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row single">
                        <div class="checkbox-item">
                            <input type="checkbox" name="include_headers" id="include_headers" checked>
                            <label for="include_headers">Sütun başlıklarını dahil et</label>
                            <div class="help-text">İlk satırda alan adları</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>Export İşlemi</h2>
                
                <div class="alert alert-info">
                    <strong>Bilgi:</strong> Export işlemi seçilen kriterlere göre kullanıcı verilerini dosya olarak indirecektir.
                    Büyük veri setleri için işlem biraz zaman alabilir.
                </div>
                
                <div style="text-align: center;">
                    <button type="submit" class="btn btn-success btn-large">
                        📥 Export'u Başlat
                    </button>
                </div>
                
                <div style="margin-top: 30px; text-align: center;">
                    <a href="users.php" class="btn btn-secondary">👥 Kullanıcı Listesine Dön</a>
                </div>
            </div>
        </form>
    </div>

    <script>
    function selectAllFields() {
        document.querySelectorAll('input[name="export_fields[]"]').forEach(cb => cb.checked = true);
    }
    
    function selectNoFields() {
        document.querySelectorAll('input[name="export_fields[]"]').forEach(cb => cb.checked = false);
    }
    
    function selectBasicFields() {
        selectNoFields();
        ['id', 'username', 'email', 'full_name', 'role', 'is_active', 'created_at'].forEach(field => {
            const cb = document.getElementById('field_' + field);
            if (cb) cb.checked = true;
        });
    }
    
    // Export türü değişiminde filtre bölümünü göster/gizle
    document.querySelectorAll('input[name="type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const filterSection = document.getElementById('filterSection');
            if (this.value === 'filtered') {
                filterSection.style.display = 'block';
            } else {
                filterSection.style.display = 'none';
            }
        });
    });
    
    // Form submit kontrolü
    document.getElementById('exportForm').addEventListener('submit', function(e) {
        const selectedFields = document.querySelectorAll('input[name="export_fields[]"]:checked');
        if (selectedFields.length === 0) {
            e.preventDefault();
            alert('Lütfen en az bir alan seçin!');
            return false;
        }
        
        return confirm('Export işlemini başlatmak istediğinizden emin misiniz?');
    });
    </script>
</body>
</html>