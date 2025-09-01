<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webimvar - Kontrol Paneli</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f8f9fa; }
        .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 1rem 0; }
        .header .container { max-width: 1200px; margin: 0 auto; padding: 0 1rem; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: bold; }
        .user-info { display: flex; align-items: center; gap: 1rem; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem 1rem; }
        .dashboard { display: grid; grid-template-columns: 250px 1fr; gap: 2rem; }
        .sidebar { background: white; padding: 1.5rem; border-radius: 10px; height: fit-content; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .main-content { background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); min-height: 600px; }
        .menu-item { padding: 0.8rem 1rem; margin-bottom: 0.5rem; border-radius: 5px; cursor: pointer; transition: background 0.3s; }
        .menu-item:hover { background: #f8f9fa; }
        .menu-item.active { background: #667eea; color: white; }
        .content-section { display: none; }
        .content-section.active { display: block; }
        .form-section { margin-bottom: 2rem; }
        .form-section h3 { margin-bottom: 1rem; color: #333; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: #555; font-weight: 500; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 0.9rem; }
        .form-group textarea { height: 100px; resize: vertical; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .btn { padding: 12px 24px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 1rem; transition: transform 0.3s; }
        .btn:hover { transform: translateY(-2px); }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #545b62; }
        .success { color: #27ae60; margin-top: 1rem; padding: 10px; background: #f2fdf2; border-radius: 5px; }
        .error { color: #e74c3c; margin-top: 1rem; padding: 10px; background: #fdf2f2; border-radius: 5px; }
        .site-info { background: #e8f4fd; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; }
        .site-status { display: inline-block; padding: 5px 10px; border-radius: 15px; font-size: 0.8rem; font-weight: bold; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-creating { background: #cce5ff; color: #004085; }
        .status-ready { background: #d1edff; color: #0c5460; }
        .status-active { background: #d4edda; color: #155724; }
        @media (max-width: 768px) {
            .dashboard { grid-template-columns: 1fr; }
            .sidebar { order: 2; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="logo">webimvar</div>
            <div class="user-info">
                <span><?php echo $_SESSION['email']; ?></span>
                <button class="btn btn-secondary" onclick="logout()">Çıkış</button>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="dashboard">
            <div class="sidebar">
                <div class="menu-item active" onclick="showSection('profile')">📝 Profil Bilgileri</div>
                <div class="menu-item" onclick="showSection('site')">🌐 Sitem</div>
                <div class="menu-item" onclick="showSection('payment')">💳 Ödeme</div>
            </div>

            <div class="main-content">
                <!-- Profil Section -->
                <div id="profile-section" class="content-section active">
                    <h2>Profil Bilgileri</h2>
                    
                    <div class="site-info">
                        <h3>Site Durumu: <span id="siteStatus" class="site-status status-pending">Bekliyor</span></h3>
                        <p id="siteUrl" style="margin-top: 0.5rem;">Site adresiniz kaydedilince burada görünecek</p>
                    </div>
                    
                    <form id="profileForm">
                        <div class="form-section">
                            <h3>Kişisel Bilgiler</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Ad Soyad *</label>
                                    <input type="text" id="name" required>
                                </div>
                                <div class="form-group">
                                    <label>Meslek/Unvan *</label>
                                    <input type="text" id="profession" required>
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Telefon *</label>
                                    <input type="tel" id="phone" required>
                                </div>
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" id="email" value="<?php echo $_SESSION['email']; ?>" readonly style="background:#f8f9fa;">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Hakkımda</label>
                                <textarea id="bio" placeholder="Kendinizi kısaca tanıtın..."></textarea>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>İş Bilgileri</h3>
                            <div class="form-group">
                                <label>Şirket/Mağaza Adı</label>
                                <input type="text" id="company_name">
                            </div>
                            <div class="form-group">
                                <label>Hizmetler</label>
                                <textarea id="services" placeholder="Verdiğiniz hizmetleri listeleyin..."></textarea>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Çalışma Saatleri</label>
                                    <input type="text" id="working_hours" placeholder="Örn: 09:00 - 18:00">
                                </div>
                                <div class="form-group">
                                    <label>Adres</label>
                                    <input type="text" id="address">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn">Profil Bilgilerini Kaydet</button>
                        <div id="profileMessage"></div>
                    </form>
                </div>

                <!-- Site Section -->
                <div id="site-section" class="content-section">
                    <h2>Sitem</h2>
                    <div class="site-info">
                        <h3>Site Bilgileri</h3>
                        <p><strong>Durum:</strong> <span id="siteStatusDetail">Bekliyor</span></p>
                        <p><strong>Adres:</strong> <span id="siteUrlDetail">Henüz oluşturulmadı</span></p>
                        <p><strong>Son Güncelleme:</strong> <span id="lastUpdate">-</span></p>
                    </div>
                    <div class="form-group">
                        <button class="btn" onclick="openSite()" id="openSiteBtn" disabled>Sitemi Görüntüle</button>
                    </div>
                </div>

                <!-- Payment Section -->
                <div id="payment-section" class="content-section">
                    <h2>Ödeme Bilgileri</h2>
                    <div class="site-info">
                        <h3>Abonelik Durumu</h3>
                        <p><strong>Plan:</strong> Dijital Kartvizit (99₺/ay)</p>
                        <p><strong>Durum:</strong> <span class="site-status status-active">Aktif</span></p>
                        <p><strong>Sonraki Ödeme:</strong> 29 Şubat 2025</p>
                    </div>
                    <p style="margin-top: 1rem; color: #6c757d;">
                        Aboneliği iptal etmek için 1 ay önceden WhatsApp'tan haber vermeniz yeterli.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentProfile = null;

        // Section gösterme
        function showSection(section) {
            document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.menu-item').forEach(m => m.classList.remove('active'));
            
            document.getElementById(section + '-section').classList.add('active');
            event.target.classList.add('active');
        }

        // Profil form submit
        document.getElementById('profileForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = {
                name: document.getElementById('name').value,
                profession: document.getElementById('profession').value,
                phone: document.getElementById('phone').value,
                email: document.getElementById('email').value,
                bio: document.getElementById('bio').value,
                company_name: document.getElementById('company_name').value,
                services: document.getElementById('services').value,
                working_hours: document.getElementById('working_hours').value,
                address: document.getElementById('address').value
            };
            
            try {
                const response = await fetch('../api/profile.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                const messageDiv = document.getElementById('profileMessage');
                
                if (data.success) {
                    messageDiv.innerHTML = '<div class="success">✓ Profil kaydedildi! Siteniz 48 saat içinde hazır olacak.</div>';
                    if (data.site_url) {
                        updateSiteInfo(data.subdomain, data.site_url, 'creating');
                    }
                    loadProfile(); // Profili yeniden yükle
                } else {
                    messageDiv.innerHTML = '<div class="error">Hata: ' + data.error + '</div>';
                }
            } catch (error) {
                document.getElementById('profileMessage').innerHTML = '<div class="error">Bir hata oluştu, tekrar deneyin.</div>';
            }
        });

        // Site bilgilerini güncelle
        function updateSiteInfo(subdomain, siteUrl, status) {
            const statusElement = document.getElementById('siteStatus');
            const urlElement = document.getElementById('siteUrl');
            const statusDetail = document.getElementById('siteStatusDetail');
            const urlDetail = document.getElementById('siteUrlDetail');
            const openBtn = document.getElementById('openSiteBtn');
            
            let statusText, statusClass;
            switch(status) {
                case 'pending':
                    statusText = 'Bekliyor';
                    statusClass = 'status-pending';
                    break;
                case 'creating':
                    statusText = 'Oluşturuluyor';
                    statusClass = 'status-creating';
                    break;
                case 'ready':
                    statusText = 'Hazır';
                    statusClass = 'status-ready';
                    openBtn.disabled = false;
                    break;
                case 'active':
                    statusText = 'Aktif';
                    statusClass = 'status-active';
                    openBtn.disabled = false;
                    break;
            }
            
            statusElement.textContent = statusText;
            statusElement.className = 'site-status ' + statusClass;
            statusDetail.textContent = statusText;
            
            if (siteUrl) {
                urlElement.textContent = siteUrl;
                urlDetail.innerHTML = '<a href="' + siteUrl + '" target="_blank">' + siteUrl + '</a>';
            }
        }

        // Site açma
        function openSite() {
            if (currentProfile && currentProfile.subdomain) {
                window.open('https://' + currentProfile.subdomain + '.webimvar.com', '_blank');
            }
        }

        // Profil yükleme
        async function loadProfile() {
            try {
                const response = await fetch('../api/get-profile.php');
                const data = await response.json();
                
                if (data.success && data.profile) {
                    currentProfile = data.profile;
                    const profile = data.profile;
                    
                    document.getElementById('name').value = profile.name || '';
                    document.getElementById('profession').value = profile.profession || '';
                    document.getElementById('phone').value = profile.phone || '';
                    document.getElementById('bio').value = profile.bio || '';
                    document.getElementById('company_name').value = profile.company_name || '';
                    document.getElementById('services').value = profile.services || '';
                    document.getElementById('working_hours').value = profile.working_hours || '';
                    document.getElementById('address').value = profile.address || '';
                    
                    if (profile.subdomain) {
                        const siteUrl = 'https://' + profile.subdomain + '.webimvar.com';
                        updateSiteInfo(profile.subdomain, siteUrl, profile.site_status || 'pending');
                        
                        document.getElementById('lastUpdate').textContent = 
                            new Date(profile.updated_at).toLocaleDateString('tr-TR');
                    }
                }
            } catch (error) {
                console.log('Profil yükleme hatası:', error);
            }
        }

        // Logout
        function logout() {
            fetch('../api/logout.php', { method: 'POST' })
            .then(() => {
                window.location.href = 'login.php';
            });
        }

        // Sayfa yüklendiğinde profil bilgilerini yükle
        window.addEventListener('DOMContentLoaded', loadProfile);
    </script>
</body>
</html>