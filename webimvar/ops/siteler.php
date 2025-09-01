<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: giris.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Yönetimi - Ops Panel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f8f9fa; }
        .header { background: linear-gradient(135deg, #2c3e50, #34495e); color: white; padding: 1rem 0; }
        .header .container { max-width: 1200px; margin: 0 auto; padding: 0 1rem; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: bold; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem 1rem; }
        .dashboard { display: grid; grid-template-columns: 250px 1fr; gap: 2rem; }
        .sidebar { background: white; padding: 1.5rem; border-radius: 10px; height: fit-content; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .main-content { background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .menu-item { padding: 0.8rem 1rem; margin-bottom: 0.5rem; border-radius: 5px; cursor: pointer; transition: background 0.3s; text-decoration: none; color: #333; display: block; }
        .menu-item:hover { background: #f8f9fa; }
        .menu-item.active { background: #2c3e50; color: white; }
        .search-box { width: 300px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 2rem; }
        .sites-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .sites-table th, .sites-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .sites-table th { background: #f8f9fa; font-weight: 600; }
        .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: bold; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-creating { background: #cce5ff; color: #004085; }
        .status-ready { background: #d1edff; color: #0c5460; }
        .status-active { background: #d4edda; color: #155724; }
        .btn { padding: 6px 12px; background: #2c3e50; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.8rem; }
        .btn-sm { padding: 4px 8px; font-size: 0.7rem; }
        .btn-success { background: #27ae60; }
        .btn-warning { background: #f39c12; }
        .btn-danger { background: #e74c3c; }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="logo">🔒 OPS PANEL</div>
        </div>
    </div>

    <div class="container">
        <div class="dashboard">
            <div class="sidebar">
                <a href="panel.php" class="menu-item">📊 Dashboard</a>
                <a href="kullanicilar.php" class="menu-item">👥 Kullanıcılar</a>
                <a href="siteler.php" class="menu-item active">🌐 Siteler</a>
                <a href="raporlar.php" class="menu-item">📈 Raporlar</a>
            </div>

            <div class="main-content">
                <h2>Site Yönetimi</h2>
                
                <input type="text" class="search-box" placeholder="Site ara..." id="searchBox">
                
                <table class="sites-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kullanıcı</th>
                            <th>Site Adı</th>
                            <th>Subdomain</th>
                            <th>Durum</th>
                            <th>Oluşturma Tarihi</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody id="sitesTable">
                        <tr><td colspan="7" style="text-align:center;">Yükleniyor...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        async function loadSites(search = '') {
            try {
                const response = await fetch(`api/admin-sites.php?search=${search}`);
                const data = await response.json();
                
                if (data.success) {
                    const tbody = document.getElementById('sitesTable');
                    tbody.innerHTML = data.sites.map(site => `
                        <tr>
                            <td>${site.user_id}</td>
                            <td>${site.name || site.email}</td>
                            <td>${site.name} - ${site.profession}</td>
                            <td>
                                ${site.subdomain ? 
                                    `<a href="https://${site.subdomain}.webimvar.com" target="_blank">${site.subdomain}</a>` : 
                                    '-'
                                }
                            </td>
                            <td><span class="status-badge status-${site.site_status}">${getStatusText(site.site_status)}</span></td>
                            <td>${formatDate(site.created_at)}</td>
                            <td>
                                <select onchange="changeSiteStatus(${site.user_id}, this.value)" class="btn btn-sm">
                                    <option value="pending" ${site.site_status === 'pending' ? 'selected' : ''}>Bekliyor</option>
                                    <option value="creating" ${site.site_status === 'creating' ? 'selected' : ''}>Oluşturuluyor</option>
                                    <option value="ready" ${site.site_status === 'ready' ? 'selected' : ''}>Hazır</option>
                                    <option value="active" ${site.site_status === 'active' ? 'selected' : ''}>Aktif</option>
                                </select>
                            </td>
                        </tr>
                    `).join('');
                }
            } catch (error) {
                document.getElementById('sitesTable').innerHTML = '<tr><td colspan="7">Yükleme hatası</td></tr>';
            }
        }

        function getStatusText(status) {
            const statusMap = {
                'pending': 'Bekliyor',
                'creating': 'Oluşturuluyor',
                'ready': 'Hazır',
                'active': 'Aktif'
            };
            return statusMap[status] || status;
        }

        function formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('tr-TR');
        }

        async function changeSiteStatus(userId, newStatus) {
            try {
                const response = await fetch('api/admin-site-status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: userId, site_status: newStatus })
                });
                
                const data = await response.json();
                if (data.success) {
                    loadSites();
                } else {
                    alert('Hata: ' + data.error);
                }
            } catch (error) {
                alert('Bağlantı hatası');
            }
        }

        document.getElementById('searchBox').addEventListener('input', (e) => {
            setTimeout(() => loadSites(e.target.value), 300);
        });

        window.addEventListener('DOMContentLoaded', () => loadSites());
    </script>
</body>
</html>