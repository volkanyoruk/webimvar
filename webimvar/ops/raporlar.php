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
    <title>Raporlar - Ops Panel</title>
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
        .report-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-bottom: 3rem; }
        .report-card { background: #f8f9fa; padding: 2rem; border-radius: 10px; border-left: 4px solid #2c3e50; }
        .report-card h3 { margin-bottom: 1rem; color: #2c3e50; }
        .report-number { font-size: 2.5rem; font-weight: bold; color: #2c3e50; margin-bottom: 0.5rem; }
        .report-label { color: #666; font-size: 0.9rem; }
        .chart-container { background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .date-filter { margin-bottom: 2rem; }
        .date-filter select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-right: 1rem; }
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
                <a href="siteler.php" class="menu-item">🌐 Siteler</a>
                <a href="raporlar.php" class="menu-item active">📈 Raporlar</a>
            </div>

            <div class="main-content">
                <h2>Raporlar ve Analitikler</h2>
                
                <div class="date-filter">
                    <select id="periodFilter">
                        <option value="7">Son 7 Gün</option>
                        <option value="30" selected>Son 30 Gün</option>
                        <option value="90">Son 3 Ay</option>
                        <option value="365">Son 1 Yıl</option>
                    </select>
                    <button onclick="loadReports()" style="padding: 8px 16px; background: #2c3e50; color: white; border: none; border-radius: 4px;">Güncelle</button>
                </div>

                <div class="report-grid" id="reportGrid">
                    <div class="report-card">
                        <h3>Toplam Gelir</h3>
                        <div class="report-number" id="totalRevenue">-</div>
                        <div class="report-label">Son 30 günde</div>
                    </div>
                    
                    <div class="report-card">
                        <h3>Yeni Kayıtlar</h3>
                        <div class="report-number" id="newUsers">-</div>
                        <div class="report-label">Son 30 günde</div>
                    </div>
                    
                    <div class="report-card">
                        <h3>Aktif Siteler</h3>
                        <div class="report-number" id="activeSites">-</div>
                        <div class="report-label">Şu anda</div>
                    </div>
                    
                    <div class="report-card">
                        <h3>Churn Rate</h3>
                        <div class="report-number" id="churnRate">-</div>
                        <div class="report-label">Aylık iptal oranı</div>
                    </div>
                </div>

                <div class="chart-container">
                    <h3>Aylık Büyüme Trendi</h3>
                    <div id="growthChart" style="height: 300px; display: flex; align-items: center; justify-content: center; color: #666;">
                        Grafik yükleniyor...
                    </div>
                </div>

                <div class="chart-container">
                    <h3>Site Durumları</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-top: 1rem;">
                        <div style="text-align: center; padding: 1rem; background: #fff3cd; border-radius: 8px;">
                            <div style="font-size: 2rem; font-weight: bold; color: #856404;" id="pendingSitesCount">-</div>
                            <div style="color: #856404; font-size: 0.9rem;">Bekliyor</div>
                        </div>
                        <div style="text-align: center; padding: 1rem; background: #cce5ff; border-radius: 8px;">
                            <div style="font-size: 2rem; font-weight: bold; color: #004085;" id="creatingSitesCount">-</div>
                            <div style="color: #004085; font-size: 0.9rem;">Oluşturuluyor</div>
                        </div>
                        <div style="text-align: center; padding: 1rem; background: #d1edff; border-radius: 8px;">
                            <div style="font-size: 2rem; font-weight: bold; color: #0c5460;" id="readySitesCount">-</div>
                            <div style="color: #0c5460; font-size: 0.9rem;">Hazır</div>
                        </div>
                        <div style="text-align: center; padding: 1rem; background: #d4edda; border-radius: 8px;">
                            <div style="font-size: 2rem; font-weight: bold; color: #155724;" id="activeSitesCount">-</div>
                            <div style="color: #155724; font-size: 0.9rem;">Aktif</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        async function loadReports() {
            const period = document.getElementById('periodFilter').value;
            
            try {
                const response = await fetch(`api/admin-reports.php?period=${period}`);
                const data = await response.json();
                
                if (data.success) {
                    const reports = data.reports;
                    
                    // Ana metrikleri güncelle
                    document.getElementById('totalRevenue').textContent = reports.totalRevenue + '₺';
                    document.getElementById('newUsers').textContent = reports.newUsers;
                    document.getElementById('activeSites').textContent = reports.activeSites;
                    document.getElementById('churnRate').textContent = reports.churnRate + '%';
                    
                    // Site durumlarını güncelle
                    document.getElementById('pendingSitesCount').textContent = reports.siteStatus.pending || 0;
                    document.getElementById('creatingSitesCount').textContent = reports.siteStatus.creating || 0;
                    document.getElementById('readySitesCount').textContent = reports.siteStatus.ready || 0;
                    document.getElementById('activeSitesCount').textContent = reports.siteStatus.active || 0;
                    
                    // Grafik placeholder güncelle
                    document.getElementById('growthChart').innerHTML = 
                        '<div style="text-align: center;"><h4>Son ' + period + ' günde ' + reports.newUsers + ' yeni kullanıcı</h4><p>Günlük ortalama: ' + Math.round(reports.newUsers / period) + ' kayıt</p></div>';
                }
            } catch (error) {
                console.error('Rapor yükleme hatası:', error);
            }
        }

        window.addEventListener('DOMContentLoaded', () => loadReports());
    </script>
</body>
</html>