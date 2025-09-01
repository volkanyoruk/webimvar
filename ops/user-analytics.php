<?php
// Kullanıcı Analitiği ve Raporlama
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

// Mevcut tabloları kontrol et
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

// Temel istatistikler
$stats = [];

// Kullanıcı sayıları
$stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

if (in_array('is_active', $columns)) {
    $stats['active_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
    $stats['inactive_users'] = $stats['total_users'] - $stats['active_users'];
} else {
    $stats['active_users'] = $stats['total_users'];
    $stats['inactive_users'] = 0;
}

// Aylık kayıt oranı
if (in_array('created_at', $columns)) {
    $stats['monthly_registrations'] = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $stats['weekly_registrations'] = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    $stats['daily_registrations'] = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn();
}

// Rol dağılımı
$role_distribution = [];
if (in_array('role', $columns)) {
    $role_query = "SELECT role, COUNT(*) as count FROM users WHERE role IS NOT NULL GROUP BY role ORDER BY count DESC";
    $role_result = $pdo->query($role_query);
    $role_distribution = $role_result->fetchAll();
}

// Aylık kayıt grafiği için veriler (son 12 ay)
$monthly_data = [];
if (in_array('created_at', $columns)) {
    $monthly_query = "
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ";
    $monthly_result = $pdo->query($monthly_query);
    $monthly_data = $monthly_result->fetchAll();
}

// Paket dağılımı
$package_distribution = [];
if (in_array('package_id', $columns) && in_array('packages', $tables)) {
    $package_query = "
        SELECT 
            p.name as package_name,
            COUNT(u.id) as user_count
        FROM packages p
        LEFT JOIN users u ON p.id = u.package_id
        GROUP BY p.id, p.name
        ORDER BY user_count DESC
    ";
    $package_result = $pdo->query($package_query);
    $package_distribution = $package_result->fetchAll();
}

// Site sayısı istatistikleri
$site_stats = [];
if (in_array('sites', $tables)) {
    $site_stats['total_sites'] = $pdo->query("SELECT COUNT(*) FROM sites")->fetchColumn();
    $site_stats['active_sites'] = $pdo->query("SELECT COUNT(*) FROM sites WHERE status = 'active'")->fetchColumn();
    $site_stats['suspended_sites'] = $pdo->query("SELECT COUNT(*) FROM sites WHERE status = 'suspended'")->fetchColumn();
    
    // Kullanıcı başına ortalama site sayısı
    $avg_sites = $pdo->query("
        SELECT AVG(site_count) as avg_sites 
        FROM (
            SELECT user_id, COUNT(*) as site_count 
            FROM sites 
            GROUP BY user_id
        ) as user_sites
    ")->fetchColumn();
    $site_stats['avg_sites_per_user'] = round($avg_sites, 2);
}

// En aktif kullanıcılar
$top_users = [];
if (in_array('last_login', $columns)) {
    $top_users_query = "
        SELECT username, email, full_name, last_login, login_count
        FROM users 
        WHERE last_login IS NOT NULL 
        ORDER BY last_login DESC 
        LIMIT 10
    ";
    try {
        $top_users = $pdo->query($top_users_query)->fetchAll();
    } catch (Exception $e) {
        // login_count kolonu yoksa basit sorgu
        $simple_query = "
            SELECT username, email, full_name, last_login
            FROM users 
            WHERE last_login IS NOT NULL 
            ORDER BY last_login DESC 
            LIMIT 10
        ";
        $top_users = $pdo->query($simple_query)->fetchAll();
    }
}

// Filtreleme
$date_filter = $_GET['date_filter'] ?? '30';
$role_filter = $_GET['role_filter'] ?? '';

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Analitiği - Webimvar SaaS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f9fa; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { color: #2c3e50; margin-bottom: 5px; }
        .header p { color: #6c757d; }
        
        .filters { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 5px; font-weight: 500; color: #495057; }
        .form-control { padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 4px; }
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.9rem; }
        .btn-primary { background: #007bff; color: white; }
        .btn:hover { opacity: 0.9; }
        
        .stats-overview { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .stat-title { color: #6c757d; font-size: 0.9rem; font-weight: 500; }
        .stat-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .stat-number { font-size: 2rem; font-weight: bold; margin-bottom: 5px; }
        .stat-change { font-size: 0.8rem; }
        .stat-positive { color: #28a745; }
        .stat-negative { color: #dc3545; }
        
        .icon-users { background: #e3f2fd; color: #1976d2; }
        .icon-active { background: #e8f5e8; color: #2e7d32; }
        .icon-inactive { background: #fff3e0; color: #f57c00; }
        .icon-new { background: #f3e5f5; color: #7b1fa2; }
        
        .charts-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px; }
        .chart-card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .chart-card h3 { color: #2c3e50; margin-bottom: 20px; }
        
        .chart-placeholder { height: 300px; background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #6c757d; }
        
        .role-chart { display: flex; flex-direction: column; gap: 15px; }
        .role-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #f8f9fa; border-radius: 6px; }
        .role-name { font-weight: 500; }
        .role-count { background: #007bff; color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; }
        
        .tables-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .table-card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .table-card h3 { color: #2c3e50; margin-bottom: 15px; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #f8f9fa; font-weight: 600; color: #495057; font-size: 0.9rem; }
        tr:hover { background: #f8f9fa; }
        
        .package-item { display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f8f9fa; border-radius: 6px; margin-bottom: 8px; }
        .package-name { font-weight: 500; color: #2c3e50; }
        .package-count { background: #28a745; color: white; padding: 4px 12px; border-radius: 16px; font-size: 0.85rem; font-weight: 500; }
        
        .empty-state { text-align: center; padding: 40px; color: #6c757d; }
        .empty-state h4 { margin-bottom: 8px; }
        
        @media (max-width: 768px) {
            .charts-grid { grid-template-columns: 1fr; }
            .tables-grid { grid-template-columns: 1fr; }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Kullanıcı Analitiği</h1>
            <p>Detaylı kullanıcı istatistikleri ve trend analizi</p>
        </div>

        <div class="filters">
            <form method="GET" class="filter-row">
                <div class="form-group">
                    <label>Zaman Aralığı</label>
                    <select name="date_filter" class="form-control">
                        <option value="7" <?= $date_filter == '7' ? 'selected' : '' ?>>Son 7 gün</option>
                        <option value="30" <?= $date_filter == '30' ? 'selected' : '' ?>>Son 30 gün</option>
                        <option value="90" <?= $date_filter == '90' ? 'selected' : '' ?>>Son 90 gün</option>
                        <option value="365" <?= $date_filter == '365' ? 'selected' : '' ?>>Son 1 yıl</option>
                    </select>
                </div>
                <?php if (!empty($role_distribution)): ?>
                <div class="form-group">
                    <label>Rol Filtresi</label>
                    <select name="role_filter" class="form-control">
                        <option value="">Tüm Roller</option>
                        <?php foreach ($role_distribution as $role): ?>
                            <option value="<?= htmlspecialchars($role['role']) ?>" <?= $role_filter == $role['role'] ? 'selected' : '' ?>>
                                <?= ucfirst($role['role']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Filtrele</button>
                </div>
            </form>
        </div>

        <!-- Genel İstatistikler -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Toplam Kullanıcı</div>
                    <div class="stat-icon icon-users">👥</div>
                </div>
                <div class="stat-number"><?= number_format($stats['total_users']) ?></div>
                <div class="stat-change stat-positive">Sistemde kayıtlı</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Aktif Kullanıcı</div>
                    <div class="stat-icon icon-active">✅</div>
                </div>
                <div class="stat-number"><?= number_format($stats['active_users']) ?></div>
                <div class="stat-change stat-positive">
                    %<?= $stats['total_users'] > 0 ? round(($stats['active_users'] / $stats['total_users']) * 100, 1) : 0 ?> aktif oran
                </div>
            </div>
            
            <?php if ($stats['inactive_users'] > 0): ?>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Pasif Kullanıcı</div>
                    <div class="stat-icon icon-inactive">⏸️</div>
                </div>
                <div class="stat-number"><?= number_format($stats['inactive_users']) ?></div>
                <div class="stat-change stat-negative">Pasifleştirilmiş</div>
            </div>
            <?php endif; ?>
            
            <?php if (isset($stats['monthly_registrations'])): ?>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Aylık Kayıt</div>
                    <div class="stat-icon icon-new">📈</div>
                </div>
                <div class="stat-number"><?= number_format($stats['monthly_registrations']) ?></div>
                <div class="stat-change stat-positive">Son 30 günde</div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($site_stats)): ?>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Toplam Site</div>
                    <div class="stat-icon icon-active">🌐</div>
                </div>
                <div class="stat-number"><?= number_format($site_stats['total_sites']) ?></div>
                <div class="stat-change">Ortalama: <?= $site_stats['avg_sites_per_user'] ?>/kullanıcı</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Grafikler -->
        <div class="charts-grid">
            <div class="chart-card">
                <h3>Aylık Kullanıcı Kayıtları (Son 12 Ay)</h3>
                <?php if (!empty($monthly_data)): ?>
                    <canvas id="monthlyChart" width="400" height="200"></canvas>
                <?php else: ?>
                    <div class="chart-placeholder">
                        Yeterli veri yok - En az birkaç aylık kayıt gerekli
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="chart-card">
                <h3>Rol Dağılımı</h3>
                <?php if (!empty($role_distribution)): ?>
                    <div class="role-chart">
                        <?php foreach ($role_distribution as $role): ?>
                            <div class="role-item">
                                <span class="role-name"><?= ucfirst($role['role']) ?></span>
                                <span class="role-count"><?= $role['count'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <h4>Rol bilgisi yok</h4>
                        <p>Kullanıcılarda rol bilgisi bulunmuyor</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Detaylı Tablolar -->
        <div class="tables-grid">
            <!-- Paket Dağılımı -->
            <?php if (!empty($package_distribution)): ?>
            <div class="table-card">
                <h3>Paket Dağılımı</h3>
                <?php foreach ($package_distribution as $package): ?>
                    <div class="package-item">
                        <span class="package-name"><?= htmlspecialchars($package['package_name']) ?></span>
                        <span class="package-count"><?= $package['user_count'] ?> kullanıcı</span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- En Aktif Kullanıcılar -->
            <?php if (!empty($top_users)): ?>
            <div class="table-card">
                <h3>Son Aktif Kullanıcılar</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Kullanıcı</th>
                            <th>Son Giriş</th>
                            <?php if (isset($top_users[0]['login_count'])): ?>
                                <th>Giriş Sayısı</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($top_users, 0, 8) as $user): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($user['username'] ?? 'N/A') ?></strong><br>
                                    <small><?= htmlspecialchars($user['email'] ?? '') ?></small>
                                </td>
                                <td><?= $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) : 'Hiç' ?></td>
                                <?php if (isset($user['login_count'])): ?>
                                    <td><?= number_format($user['login_count'] ?? 0) ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Hızlı Erişim -->
        <div style="text-align: center; margin-top: 30px;">
            <a href="users.php" class="btn btn-primary" style="padding: 12px 24px;">Kullanıcı Listesi</a>
            <a href="user-add.php" class="btn btn-primary" style="padding: 12px 24px;">Yeni Kullanıcı</a>
            <a href="user-import.php" class="btn btn-primary" style="padding: 12px 24px;">Toplu İçe Aktarma</a>
            <a href="dashboard.php" class="btn btn-primary" style="padding: 12px 24px;">Dashboard</a>
        </div>
    </div>

    <script>
    <?php if (!empty($monthly_data)): ?>
    // Aylık kayıt grafiği
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    const monthlyChart = new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: [<?php foreach($monthly_data as $month): ?>'<?= date('M Y', strtotime($month['month'] . '-01')) ?>',<?php endforeach; ?>],
            datasets: [{
                label: 'Aylık Kayıtlar',
                data: [<?php foreach($monthly_data as $month): ?><?= $month['count'] ?>,<?php endforeach; ?>],
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    <?php endif; ?>
    </script>
</body>
</html>