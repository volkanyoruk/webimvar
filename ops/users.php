<?php
// Gelişmiş SaaS Kullanıcı Yönetim Sistemi
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

// Sütun haritalaması
$columns = [];
$column_result = $pdo->query("DESCRIBE users");
while($row = $column_result->fetch()) {
    $columns[] = $row['Field'];
}

$field_map = [
    'id' => 'id',
    'username' => in_array('username', $columns) ? 'username' : 'id',
    'email' => in_array('email', $columns) ? 'email' : null,
    'full_name' => in_array('full_name', $columns) ? 'full_name' : null,
    'role' => in_array('role', $columns) ? 'role' : null,
    'status' => in_array('is_active', $columns) ? 'is_active' : (in_array('status', $columns) ? 'status' : null),
    'created_at' => in_array('created_at', $columns) ? 'created_at' : null,
    'last_login' => in_array('last_login', $columns) ? 'last_login' : null
];

// Filtreleme ve arama parametreleri
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Toplu işlemler
$message = '';
$error = '';

if ($_POST['bulk_action'] ?? false) {
    $selected_users = $_POST['selected_users'] ?? [];
    $bulk_action = $_POST['bulk_action'];
    
    if (!empty($selected_users)) {
        $user_ids = implode(',', array_map('intval', $selected_users));
        
        try {
            switch ($bulk_action) {
                case 'activate':
                    if ($field_map['status']) {
                        $status_value = ($field_map['status'] == 'is_active') ? 1 : 'active';
                        $pdo->exec("UPDATE users SET {$field_map['status']} = '$status_value' WHERE id IN ($user_ids)");
                        $message = count($selected_users) . " kullanıcı aktifleştirildi.";
                    }
                    break;
                case 'deactivate':
                    if ($field_map['status']) {
                        $status_value = ($field_map['status'] == 'is_active') ? 0 : 'inactive';
                        $pdo->exec("UPDATE users SET {$field_map['status']} = '$status_value' WHERE id IN ($user_ids)");
                        $message = count($selected_users) . " kullanıcı pasifleştirildi.";
                    }
                    break;
                case 'delete':
                    $pdo->exec("DELETE FROM users WHERE id IN ($user_ids)");
                    $message = count($selected_users) . " kullanıcı silindi.";
                    break;
                case 'export':
                    // Export functionality will be handled by JavaScript
                    break;
            }
        } catch (Exception $e) {
            $error = "Toplu işlem hatası: " . $e->getMessage();
        }
    } else {
        $error = "Hiçbir kullanıcı seçilmedi.";
    }
}

// Arama ve filtreleme sorgusu oluştur
$where_conditions = [];
$params = [];

if ($search) {
    $search_fields = [];
    if ($field_map['username']) $search_fields[] = "{$field_map['username']} LIKE ?";
    if ($field_map['email']) $search_fields[] = "{$field_map['email']} LIKE ?";
    if ($field_map['full_name']) $search_fields[] = "{$field_map['full_name']} LIKE ?";
    
    if (!empty($search_fields)) {
        $where_conditions[] = "(" . implode(" OR ", $search_fields) . ")";
        for ($i = 0; $i < count($search_fields); $i++) {
            $params[] = "%$search%";
        }
    }
}

if ($role_filter && $field_map['role']) {
    $where_conditions[] = "{$field_map['role']} = ?";
    $params[] = $role_filter;
}

if ($status_filter && $field_map['status']) {
    if ($field_map['status'] == 'is_active') {
        $where_conditions[] = "{$field_map['status']} = ?";
        $params[] = ($status_filter == 'active') ? 1 : 0;
    } else {
        $where_conditions[] = "{$field_map['status']} = ?";
        $params[] = $status_filter;
    }
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Toplam sayıyı hesapla
$count_sql = "SELECT COUNT(*) FROM users $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_users = $count_stmt->fetchColumn();
$total_pages = ceil($total_users / $limit);

// Ana sorgu
$select_fields = ['id'];
if ($field_map['username']) $select_fields[] = "{$field_map['username']} as username";
if ($field_map['email']) $select_fields[] = "{$field_map['email']} as email";
if ($field_map['full_name']) $select_fields[] = "{$field_map['full_name']} as full_name";
if ($field_map['role']) $select_fields[] = "{$field_map['role']} as role";
if ($field_map['status']) $select_fields[] = "{$field_map['status']} as status";
if ($field_map['created_at']) $select_fields[] = "{$field_map['created_at']} as created_at";
if ($field_map['last_login']) $select_fields[] = "{$field_map['last_login']} as last_login";

$main_sql = "SELECT " . implode(', ', $select_fields) . " FROM users $where_clause ORDER BY id DESC LIMIT $limit OFFSET $offset";
$main_stmt = $pdo->prepare($main_sql);
$main_stmt->execute($params);
$users = $main_stmt->fetchAll();

// İstatistikleri hesapla
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'active' => 0,
    'inactive' => 0,
    'today_registrations' => 0
];

if ($field_map['status']) {
    if ($field_map['status'] == 'is_active') {
        $stats['active'] = $pdo->query("SELECT COUNT(*) FROM users WHERE {$field_map['status']} = 1")->fetchColumn();
        $stats['inactive'] = $stats['total'] - $stats['active'];
    } else {
        $stats['active'] = $pdo->query("SELECT COUNT(*) FROM users WHERE {$field_map['status']} = 'active'")->fetchColumn();
        $stats['inactive'] = $pdo->query("SELECT COUNT(*) FROM users WHERE {$field_map['status']} != 'active'")->fetchColumn();
    }
}

if ($field_map['created_at']) {
    $stats['today_registrations'] = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE({$field_map['created_at']}) = CURDATE()")->fetchColumn();
}

// Roller listesi (varsa)
$roles = [];
if ($field_map['role']) {
    $role_result = $pdo->query("SELECT DISTINCT {$field_map['role']} as role FROM users WHERE {$field_map['role']} IS NOT NULL ORDER BY {$field_map['role']}");
    $roles = $role_result->fetchAll(PDO::FETCH_COLUMN);
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gelişmiş Kullanıcı Yönetimi - Webimvar SaaS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f9fa; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        /* Header */
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { color: #2c3e50; margin-bottom: 5px; font-size: 1.8rem; }
        .header p { color: #6c757d; }
        
        /* Stats Cards */
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2rem; font-weight: bold; margin-bottom: 5px; }
        .stat-label { color: #6c757d; font-size: 0.9rem; }
        .stat-total { color: #007bff; }
        .stat-active { color: #28a745; }
        .stat-inactive { color: #dc3545; }
        .stat-today { color: #ffc107; }
        
        /* Filters & Search */
        .controls { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .filter-row { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 15px; margin-bottom: 15px; align-items: end; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 5px; font-weight: 500; color: #495057; font-size: 0.9rem; }
        .form-control { padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 0.9rem; }
        .form-control:focus { outline: none; border-color: #007bff; box-shadow: 0 0 0 2px rgba(0,123,255,0.25); }
        
        /* Bulk Actions */
        .bulk-actions { display: flex; gap: 10px; align-items: center; }
        .bulk-select { background: #6c757d; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; }
        .bulk-select:hover { background: #5a6268; }
        
        /* Main Table */
        .table-container { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .table-header { background: #f8f9fa; padding: 15px 20px; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; }
        .table-header h2 { color: #2c3e50; }
        .table-actions { display: flex; gap: 10px; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #f8f9fa; font-weight: 600; color: #495057; font-size: 0.85rem; }
        tr:hover { background: #f8f9fa; }
        
        /* Status badges */
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 500; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .status-1 { background: #d4edda; color: #155724; }
        .status-0 { background: #f8d7da; color: #721c24; }
        
        /* Role badges */
        .role-badge { background: #e9ecef; color: #495057; padding: 2px 6px; border-radius: 3px; font-size: 0.75rem; text-transform: capitalize; }
        .role-admin { background: #f8d7da; color: #721c24; }
        .role-manager { background: #fff3cd; color: #856404; }
        .role-user { background: #d1ecf1; color: #0c5460; }
        
        /* Buttons */
        .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.8rem; display: inline-block; margin: 2px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn:hover { opacity: 0.8; }
        
        /* Pagination */
        .pagination { display: flex; justify-content: center; align-items: center; gap: 5px; margin: 20px 0; }
        .page-btn { padding: 8px 12px; border: 1px solid #dee2e6; background: white; color: #007bff; text-decoration: none; border-radius: 4px; }
        .page-btn:hover { background: #e9ecef; }
        .page-btn.active { background: #007bff; color: white; border-color: #007bff; }
        .page-info { padding: 0 15px; color: #6c757d; font-size: 0.9rem; }
        
        /* Messages */
        .message { padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .filter-row { grid-template-columns: 1fr; }
            .table-container { overflow-x: auto; }
            .stats { grid-template-columns: repeat(2, 1fr); }
        }
        
        /* Activity indicators */
        .activity-online { color: #28a745; font-size: 0.8rem; }
        .activity-recent { color: #ffc107; font-size: 0.8rem; }
        .activity-offline { color: #6c757d; font-size: 0.8rem; }
        
        /* User avatar */
        .user-avatar { width: 32px; height: 32px; border-radius: 50%; background: #007bff; color: white; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.8rem; margin-right: 10px; }
        
        /* Advanced features */
        .user-details { display: flex; align-items: center; }
        .user-info { display: flex; flex-direction: column; }
        .user-name { font-weight: 600; color: #2c3e50; }
        .user-meta { font-size: 0.75rem; color: #6c757d; }
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
            <h1>👥 Gelişmiş Kullanıcı Yönetimi</h1>
            <p>Kapsamlı kullanıcı yönetim paneli - <?= number_format($total_users) ?> kullanıcı</p>
        </div>

        <!-- İstatistikler -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number stat-total"><?= number_format($stats['total']) ?></div>
                <div class="stat-label">Toplam Kullanıcı</div>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-active"><?= number_format($stats['active']) ?></div>
                <div class="stat-label">Aktif Kullanıcı</div>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-inactive"><?= number_format($stats['inactive']) ?></div>
                <div class="stat-label">Pasif Kullanıcı</div>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-today"><?= number_format($stats['today_registrations']) ?></div>
                <div class="stat-label">Bugünkü Kayıtlar</div>
            </div>
        </div>

        <!-- Arama ve Filtreler -->
        <div class="controls">
            <form method="GET" class="filter-form">
                <div class="filter-row">
                    <div class="form-group">
                        <label>Arama</label>
                        <input type="text" name="search" class="form-control" placeholder="Kullanıcı adı, email veya isim ara..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <?php if($field_map['role'] && !empty($roles)): ?>
                    <div class="form-group">
                        <label>Rol</label>
                        <select name="role" class="form-control">
                            <option value="">Tüm Roller</option>
                            <?php foreach($roles as $role): ?>
                                <option value="<?= htmlspecialchars($role) ?>" <?= $role_filter == $role ? 'selected' : '' ?>><?= ucfirst($role) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <?php if($field_map['status']): ?>
                    <div class="form-group">
                        <label>Durum</label>
                        <select name="status" class="form-control">
                            <option value="">Tüm Durumlar</option>
                            <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Aktif</option>
                            <option value="inactive" <?= $status_filter == 'inactive' ? 'selected' : '' ?>>Pasif</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Filtrele</button>
                    </div>
                </div>
            </form>
            
            <!-- Toplu İşlemler -->
            <form method="POST" id="bulkForm">
                <div class="bulk-actions">
                    <button type="button" onclick="toggleAll()" class="bulk-select">Tümünü Seç</button>
                    <select name="bulk_action" class="form-control" style="width: auto;">
                        <option value="">Toplu İşlem Seçin</option>
                        <?php if($field_map['status']): ?>
                            <option value="activate">Aktifleştir</option>
                            <option value="deactivate">Pasifleştir</option>
                        <?php endif; ?>
                        <option value="delete">Sil</option>
                        <option value="export">Dışa Aktar (CSV)</option>
                    </select>
                    <button type="submit" class="btn btn-warning" onclick="return confirm('Bu işlemi gerçekleştirmek istediğinizden emin misiniz?')">Uygula</button>
                </div>
        </div>

        <!-- Ana Tablo -->
        <div class="table-container">
            <div class="table-header">
                <h2>Kullanıcı Listesi (<?= $total_users ?> kayıt)</h2>
                <div class="table-actions">
                    <a href="user-add.php" class="btn btn-success">➕ Yeni Kullanıcı</a>
                    <a href="user-import.php" class="btn btn-info">📥 Toplu İçe Aktar</a>
                    <a href="?export=csv" class="btn btn-secondary">📤 CSV Dışa Aktar</a>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th width="40"><input type="checkbox" id="selectAll" onchange="toggleAll()"></th>
                        <th>Kullanıcı</th>
                        <?php if($field_map['email']): ?><th>Email</th><?php endif; ?>
                        <?php if($field_map['role']): ?><th>Rol</th><?php endif; ?>
                        <?php if($field_map['status']): ?><th>Durum</th><?php endif; ?>
                        <th>Aktivite</th>
                        <?php if($field_map['created_at']): ?><th>Kayıt Tarihi</th><?php endif; ?>
                        <th width="200">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): 
                        $avatar_letter = substr($user['username'] ?? 'U', 0, 1);
                        $user_id = $user['id'];
                        
                        // Durum hesapla
                        $status_display = 'unknown';
                        $status_class = 'status-unknown';
                        if($field_map['status']) {
                            if($field_map['status'] == 'is_active') {
                                $status_display = $user['status'] ? 'Aktif' : 'Pasif';
                                $status_class = 'status-' . ($user['status'] ? '1' : '0');
                            } else {
                                $status_display = ucfirst($user['status'] ?? 'unknown');
                                $status_class = 'status-' . ($user['status'] ?? 'unknown');
                            }
                        }
                        
                        // Aktivite durumu (basit hesaplama)
                        $activity_class = 'activity-offline';
                        $activity_text = 'Çevrimdışı';
                        if($field_map['last_login'] && $user['last_login']) {
                            $last_login = strtotime($user['last_login']);
                            $now = time();
                            if($now - $last_login < 300) { // 5 dakika
                                $activity_class = 'activity-online';
                                $activity_text = 'Çevrimiçi';
                            } elseif($now - $last_login < 3600) { // 1 saat
                                $activity_class = 'activity-recent';  
                                $activity_text = 'Az önce aktif';
                            }
                        }
                    ?>
                    <tr>
                        <td><input type="checkbox" name="selected_users[]" value="<?= $user_id ?>" class="user-checkbox"></td>
                        <td>
                            <div class="user-details">
                                <div class="user-avatar"><?= strtoupper($avatar_letter) ?></div>
                                <div class="user-info">
                                    <div class="user-name"><?= htmlspecialchars($user['username'] ?? 'user_' . $user_id) ?></div>
                                    <?php if($user['full_name']): ?>
                                        <div class="user-meta"><?= htmlspecialchars($user['full_name']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <?php if($field_map['email']): ?>
                        <td><?= htmlspecialchars($user['email'] ?? 'N/A') ?></td>
                        <?php endif; ?>
                        <?php if($field_map['role']): ?>
                        <td><span class="role-badge role-<?= $user['role'] ?? 'user' ?>"><?= ucfirst($user['role'] ?? 'user') ?></span></td>
                        <?php endif; ?>
                        <?php if($field_map['status']): ?>
                        <td><span class="status-badge <?= $status_class ?>"><?= $status_display ?></span></td>
                        <?php endif; ?>
                        <td><span class="<?= $activity_class ?>"><?= $activity_text ?></span></td>
                        <?php if($field_map['created_at']): ?>
                        <td><?= $user['created_at'] ? date('d.m.Y H:i', strtotime($user['created_at'])) : 'N/A' ?></td>
                        <?php endif; ?>
                        <td>
                            <a href="user-detail.php?id=<?= $user_id ?>" class="btn btn-info" title="Detaylar">👁️</a>
                            <a href="user-edit.php?id=<?= $user_id ?>" class="btn btn-primary" title="Düzenle">✏️</a>
                            <?php if($field_map['status']): ?>
                            <a href="?toggle_status=<?= $user_id ?>" class="btn btn-warning" title="Durum Değiştir">🔄</a>
                            <?php endif; ?>
                            <a href="user-impersonate.php?id=<?= $user_id ?>" class="btn btn-secondary" title="Kullanıcı Olarak Giriş">👤</a>
                            <a href="?delete=<?= $user_id ?>" class="btn btn-danger" title="Sil" onclick="return confirm('Bu kullanıcıyı silmek istediğinizden emin misiniz?')">🗑️</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </form>
        </div>

        <!-- Sayfalama -->
        <?php if($total_pages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="?page=1<?= $search ? "&search=$search" : '' ?><?= $role_filter ? "&role=$role_filter" : '' ?><?= $status_filter ? "&status=$status_filter" : '' ?>" class="page-btn">« İlk</a>
                <a href="?page=<?= $page-1 ?><?= $search ? "&search=$search" : '' ?><?= $role_filter ? "&role=$role_filter" : '' ?><?= $status_filter ? "&status=$status_filter" : '' ?>" class="page-btn">‹ Önceki</a>
            <?php endif; ?>
            
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            for($i = $start_page; $i <= $end_page; $i++):
            ?>
                <a href="?page=<?= $i ?><?= $search ? "&search=$search" : '' ?><?= $role_filter ? "&role=$role_filter" : '' ?><?= $status_filter ? "&status=$status_filter" : '' ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            
            <?php if($page < $total_pages): ?>
                <a href="?page=<?= $page+1 ?><?= $search ? "&search=$search" : '' ?><?= $role_filter ? "&role=$role_filter" : '' ?><?= $status_filter ? "&status=$status_filter" : '' ?>" class="page-btn">Sonraki ›</a>
                <a href="?page=<?= $total_pages ?><?= $search ? "&search=$search" : '' ?><?= $role_filter ? "&role=$role_filter" : '' ?><?= $status_filter ? "&status=$status_filter" : '' ?>" class="page-btn">Son »</a>
            <?php endif; ?>
            
            <div class="page-info">
                Sayfa <?= $page ?> / <?= $total_pages ?> (Toplam: <?= number_format($total_users) ?> kullanıcı)
            </div>
        </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="dashboard.php" class="btn btn-primary" style="padding: 12px 24px;">🏠 Dashboard'a Dön</a>
            <a href="packages.php" class="btn btn-success" style="padding: 12px 24px;">📦 Paket Yönetimi</a>
            <a href="user-analytics.php" class="btn btn-info" style="padding: 12px 24px;">📊 Kullanıcı Analitiği</a>
        </div>
    </div>

    <script>
    function toggleAll() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.user-checkbox');
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll.checked;
        });
    }
    
    // CSV Export functionality
    document.addEventListener('DOMContentLoaded', function() {
        const bulkForm = document.getElementById('bulkForm');
        bulkForm.addEventListener('submit', function(e) {
            const action = document.querySelector('select[name="bulk_action"]').value;
            if (action === 'export') {
                e.preventDefault();
                exportSelectedUsers();
            }
        });
    });
    
    function exportSelectedUsers() {
        const selected = document.querySelectorAll('.user-checkbox:checked');
        if (selected.length === 0) {
            alert('Lütfen dışa aktarılacak kullanıcıları seçin.');
            return;
        }
        
        const userIds = Array.from(selected).map(cb => cb.value);
        window.location.href = 'user-export.php?ids=' + userIds.join(',');
    }
    </script>
</body>
</html>