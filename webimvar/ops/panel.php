<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: giris.php');
    exit;
}

require_once '../api/config.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enterprise Admin Dashboard - Ops Panel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f7fafc; color: #2d3748; }
        
        /* Header */
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem 0; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header .container { max-width: 1400px; margin: 0 auto; padding: 0 2rem; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
        .user-info { display: flex; align-items: center; gap: 1rem; }
        .notification-badge { background: #e53e3e; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: bold; }
        
        /* Layout */
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .dashboard { display: grid; grid-template-columns: 280px 1fr; gap: 2rem; }
        
        /* Sidebar */
        .sidebar { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; height: fit-content; }
        .sidebar-header { background: #f7fafc; padding: 1.5rem; border-bottom: 1px solid #e2e8f0; }
        .sidebar-title { font-weight: 600; color: #4a5568; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em; }
        
        .menu-group { margin-bottom: 1rem; }
        .menu-group-title { padding: 1rem 1.5rem 0.5rem; font-size: 0.8rem; font-weight: 600; color: #a0aec0; text-transform: uppercase; letter-spacing: 0.05em; }
        .menu-item { padding: 0.75rem 1.5rem; display: flex; align-items: center; gap: 0.75rem; color: #4a5568; text-decoration: none; transition: all 0.2s; border-left: 3px solid transparent; }
        .menu-item:hover { background: #f7fafc; color: #2d3748; }
        .menu-item.active { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-left-color: #667eea; }
        .menu-item-icon { width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; }
        
        /* Main Content */
        .main-content { display: grid; gap: 2rem; }
        
        /* Quick Stats */
        .quick-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-left: 4px solid #667eea; }
        .stat-card.revenue { border-left-color: #48bb78; }
        .stat-card.users { border-left-color: #ed8936; }
        .stat-card.churn { border-left-color: #e53e3e; }
        .stat-header { display: flex; justify-content: between; align-items: center; margin-bottom: 1rem; }
        .stat-title { font-size: 0.9rem; color: #718096; font-weight: 500; }
        .stat-trend { font-size: 0.8rem; padding: 0.25rem 0.5rem; border-radius: 4px; font-weight: 600; }
        .trend-up { background: #c6f6d5; color: #276749; }
        .trend-down { background: #fed7d7; color: #742a2a; }
        .stat-number { font-size: 2rem; font-weight: 700; color: #2d3748; margin-bottom: 0.5rem; }
        .stat-subtitle { font-size: 0.8rem; color: #a0aec0; }
        
        /* Charts Grid */
        .charts-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; }
        .chart-card { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .chart-title { font-size: 1.1rem; font-weight: 600; color: #2d3748; }
        .chart-subtitle { font-size: 0.9rem; color: #718096; }
        
        /* Recent Activity */
        .activity-feed { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); padding: 2rem; }
        .activity-item { display: flex; align-items: start; gap: 1rem; padding: 1rem 0; border-bottom: 1px solid #f7fafc; }
        .activity-item:last-child { border-bottom: none; }
        .activity-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .activity-icon.user { background: #e6fffa; color: #319795; }
        .activity-icon.payment { background: #f0fff4; color: #38a169; }
        .activity-icon.support { background: #fef5e7; color: #d69e2e; }
        .activity-content { flex: 1; }
        .activity-title { font-weight: 600; color: #2d3748; margin-bottom: 0.25rem; }
        .activity-desc { font-size: 0.9rem; color: #718096; }
        .activity-time { font-size: 0.8rem; color: #a0aec0; }
        
        /* Buttons */
        .btn { padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s; border: none; cursor: pointer; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-secondary { background: #edf2f7; color: #4a5568; }
        .btn-success { background: #48bb78; color: white; }
        .btn-danger { background: #e53e3e; color: white; }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        
        /* Responsive */
        @media (max-width: 768px) {
            .dashboard { grid-template-columns: 1fr; }
            .charts-grid { grid-template-columns: 1fr; }
            .quick-stats { grid-template-columns: 1fr; }
        }
        
        /* Loading States */
        .loading { display: inline-block; width: 20px; height: 20px; border: 3px solid #f3f3f3; border-top: 3px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="container">
            <div class="logo">
                🚀 Webimvar Enterprise
            </div>
            <div class="user-info">
                <div class="notification-badge">3</div>
                <span>Admin: <?php echo $_SESSION['admin_username']; ?></span>
                <button class="btn btn-secondary" onclick="logout()">Çıkış</button>
            </div>
        </div>
    </div>

    <!-- Dashboard -->
    <div class="container">
        <div class="dashboard">
            <!-- Sidebar -->
            <div class="sidebar">
                <div class="sidebar-header">
                    <div class="sidebar-title">Yönetim Paneli</div>
                </div>
                
                <div class="menu-group">
                    <div class="menu-group-title">Analitik</div>
                    <a href="panel.php" class="menu-item active">
                        <div class="menu-item-icon">📊</div>
                        Dashboard
                    </a>
                    <a href="analytics.php" class="menu-item">
                        <div class="menu-item-icon">📈</div>
                        Gelişmiş Analitik
                    </a>
                </div>
                
                <div class="menu-group">
                    <div class="menu-group-title">Müşteri Yönetimi</div>
                    <a href="kullanicilar.php" class="menu-item">
                        <div class="menu-item-icon">👥</div>
                        Kullanıcılar
                    </a>
                    <a href="packages.php" class="menu-item">
                        <div class="menu-item-icon">📦</div>
                        Paket Yönetimi
                    </a>
                    <a href="billing.php" class="menu-item">
                        <div class="menu-item-icon">💳</div>
                        Faturalandırma
                    </a>
                </div>
                
                <div class="menu-group">
                    <div class="menu-group-title">İçerik</div>
                    <a href="siteler.php" class="menu-item">
                        <div class="menu-item-icon">🌐</div>
                        Site Yönetimi
                    </a>
                    <a href="templates.php" class="menu-item">
                        <div class="menu-item-icon">🎨</div>
                        Şablonlar
                    </a>
                </div>
                
                <div class="menu-group">
                    <div class="menu-group-title">Pazarlama</div>
                    <a href="campaigns.php" class="menu-item">
                        <div class="menu-item-icon">📧</div>
                        Email Kampanyaları
                    </a>
                    <a href="discounts.php" class="menu-item">
                        <div class="menu-item-icon">🎫</div>
                        İndirim Kodları
                    </a>
                </div>
                
                <div class="menu-group">
                    <div class="menu-group-title">Destek</div>
                    <a href="support.php" class="menu-item">
                        <div class="menu-item-icon">🎧</div>
                        Destek Talepleri
                    </a>
                    <a href="logs.php" class="menu-item">
                        <div class="menu-item-icon">📝</div>
                        System Logları
                    </a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <!-- Quick Stats -->
                <div class="quick-stats">
                    <div class="stat-card revenue">
                        <div class="stat-header">
                            <div class="stat-title">Toplam Gelir</div>
                            <div class="stat-trend trend-up">+12.5%</div>
                        </div>
                        <div class="stat-number" id="totalRevenue">₺0</div>
                        <div class="stat-subtitle">Bu ay</div>
                    </div>
                    
                    <div class="stat-card users">
                        <div class="stat-header">
                            <div class="stat-title">Aktif Kullanıcılar</div>
                            <div class="stat-trend trend-up">+8.2%</div>
                        </div>
                        <div class="stat-number" id="activeUsers">0</div>
                        <div class="stat-subtitle">Son 30 gün</div>
                    </div>
                    
                    <div class="stat-card churn">
                        <div class="stat-header">
                            <div class="stat-title">Churn Rate</div>
                            <div class="stat-trend trend-down">-2.1%</div>
                        </div>
                        <div class="stat-number" id="churnRate">0%</div>
                        <div class="stat-subtitle">Aylık</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-title">Ortalama Gelir</div>
                            <div class="stat-trend trend-up">+15.3%</div>
                        </div>
                        <div class="stat-number" id="arpu">₺0</div>
                        <div class="stat-subtitle">Kullanıcı başına</div>
                    </div>
                </div>

                <!-- Charts Grid -->
                <div class="charts-grid">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div>
                                <div class="chart-title">Gelir Trendi</div>
                                <div class="chart-subtitle">Son 6 ay</div>
                            </div>
                            <select id="revenueFilter" style="padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 6px;">
                                <option value="6">Son 6 Ay</option>
                                <option value="12">Son 12 Ay</option>
                                <option value="24">Son 2 Yıl</option>
                            </select>
                        </div>
                        <div id="revenueChart" style="height: 300px; display: flex; align-items: center; justify-content: center;">
                            <div class="loading"></div>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">Paket Dağılımı</div>
                        </div>
                        <div id="packageChart" style="height: 300px;">
                            <div style="display: grid; gap: 1rem; margin-top: 1rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #f7fafc; border-radius: 8px;">
                                    <div>
                                        <div style="font-weight: 600;">Basic (₺99)</div>
                                        <div style="font-size: 0.8rem; color: #718096;" id="basicCount">- kullanıcı</div>
                                    </div>
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #667eea; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;" id="basicPercent">-%</div>
                                </div>
                                
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #f0fff4; border-radius: 8px;">
                                    <div>
                                        <div style="font-weight: 600;">Premium (₺199)</div>
                                        <div style="font-size: 0.8rem; color: #718096;" id="premiumCount">- kullanıcı</div>
                                    </div>
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #48bb78; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;" id="premiumPercent">-%</div>
                                </div>
                                
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #fffaf0; border-radius: 8px;">
                                    <div>
                                        <div style="font-weight: 600;">Enterprise (₺349)</div>
                                        <div style="font-size: 0.8rem; color: #718096;" id="enterpriseCount">- kullanıcı</div>
                                    </div>
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #ed8936; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;" id="enterprisePercent">-%</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="activity-feed">
                    <div class="chart-header">
                        <div class="chart-title">Son Aktiviteler</div>
                        <button class="btn btn-secondary" onclick="refreshActivity()">Yenile</button>
                    </div>
                    <div id="activityList">
                        <div style="text-align: center; padding: 2rem; color: #a0aec0;">
                            <div class="loading"></div>
                            <div style="margin-top: 1rem;">Yükleniyor...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Dashboard veri yükleme
        async function loadDashboardData() {
            try {
                const response = await fetch('api/enterprise-stats.php');
                const data = await response.json();
                
                if (data.success) {
                    // Ana metrikler
                    document.getElementById('totalRevenue').textContent = `₺${data.stats.totalRevenue.toLocaleString('tr-TR')}`;
                    document.getElementById('activeUsers').textContent = data.stats.activeUsers;
                    document.getElementById('churnRate').textContent = `${data.stats.churnRate}%`;
                    document.getElementById('arpu').textContent = `₺${data.stats.arpu}`;
                    
                    // Paket dağılımı
                    const packages = data.stats.packageDistribution;
                    const total = packages.basic + packages.premium + packages.enterprise;
                    
                    document.getElementById('basicCount').textContent = `${packages.basic} kullanıcı`;
                    document.getElementById('premiumCount').textContent = `${packages.premium} kullanıcı`;
                    document.getElementById('enterpriseCount').textContent = `${packages.enterprise} kullanıcı`;
                    
                    if (total > 0) {
                        document.getElementById('basicPercent').textContent = `${Math.round(packages.basic/total*100)}%`;
                        document.getElementById('premiumPercent').textContent = `${Math.round(packages.premium/total*100)}%`;
                        document.getElementById('enterprisePercent').textContent = `${Math.round(packages.enterprise/total*100)}%`;
                    }
                    
                    loadRevenueChart();
                }
            } catch (error) {
                console.error('Dashboard veri yükleme hatası:', error);
            }
        }
        
        async function loadRevenueChart() {
            // Basit grafik simülasyonu
            const chartDiv = document.getElementById('revenueChart');
            const months = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz'];
            const revenues = [15000, 18000, 22000, 25000, 28000, 32000];
            
            chartDiv.innerHTML = `
                <div style="display: flex; align-items: end; gap: 1rem; height: 250px; padding: 1rem;">
                    ${months.map((month, index) => `
                        <div style="display: flex; flex-direction: column; align-items: center; flex: 1;">
                            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                                        height: ${(revenues[index]/32000)*200}px; 
                                        width: 100%; 
                                        border-radius: 4px 4px 0 0;
                                        margin-bottom: 0.5rem;"></div>
                            <div style="font-size: 0.8rem; color: #718096;">${month}</div>
                            <div style="font-size: 0.7rem; color: #a0aec0;">₺${revenues[index].toLocaleString('tr-TR')}</div>
                        </div>
                    `).join('')}
                </div>
            `;
        }
        
        async function refreshActivity() {
            const activityDiv = document.getElementById('activityList');
            activityDiv.innerHTML = '<div style="text-align: center; padding: 2rem;"><div class="loading"></div></div>';
            
            try {
                const response = await fetch('api/admin-activity.php');
                const data = await response.json();
                
                if (data.success && data.activities.length > 0) {
                    activityDiv.innerHTML = data.activities.map(activity => `
                        <div class="activity-item">
                            <div class="activity-icon ${activity.type}">
                                ${activity.type === 'user' ? '👤' : activity.type === 'payment' ? '💳' : '🎧'}
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">${activity.title}</div>
                                <div class="activity-desc">${activity.description}</div>
                                <div class="activity-time">${activity.time}</div>
                            </div>
                        </div>
                    `).join('');
                } else {
                    activityDiv.innerHTML = '<div style="text-align: center; padding: 2rem; color: #a0aec0;">Henüz aktivite yok</div>';
                }
            } catch (error) {
                activityDiv.innerHTML = '<div style="text-align: center; padding: 2rem; color: #e53e3e;">Aktiviteler yüklenemedi</div>';
            }
        }
        
        function logout() {
            if (confirm('Çıkış yapmak istediğinizden emin misiniz?')) {
                fetch('api/admin-logout.php', { method: 'POST' })
                .then(() => window.location.href = 'giris.php');
            }
        }
        
        // Sayfa yüklendiğinde veri yükle
        document.addEventListener('DOMContentLoaded', () => {
            loadDashboardData();
            refreshActivity();
            
            // Her 30 saniyede dashboard'ı güncelle
            setInterval(loadDashboardData, 30000);
        });
    </script>
</body>
</html>