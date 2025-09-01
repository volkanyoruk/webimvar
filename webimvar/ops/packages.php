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
    <title>Paket Yönetimi - Enterprise Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f7fafc; color: #2d3748; }
        
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem 0; }
        .header .container { max-width: 1400px; margin: 0 auto; padding: 0 2rem; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: 700; }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .page-title { font-size: 1.8rem; font-weight: 700; color: #2d3748; }
        
        .packages-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 2rem; margin-bottom: 3rem; }
        .package-card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; transition: transform 0.2s; }
        .package-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        
        .package-header { padding: 2rem; text-align: center; }
        .package-header.basic { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .package-header.premium { background: linear-gradient(135deg, #48bb78, #38a169); color: white; }
        .package-header.enterprise { background: linear-gradient(135deg, #ed8936, #dd6b20); color: white; }
        
        .package-name { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; }
        .package-price { font-size: 2.5rem; font-weight: 800; margin-bottom: 0.25rem; }
        .package-period { opacity: 0.8; }
        
        .package-body { padding: 2rem; }
        .package-features { list-style: none; margin-bottom: 2rem; }
        .package-features li { padding: 0.75rem 0; display: flex; align-items: center; gap: 0.75rem; border-bottom: 1px solid #f7fafc; }
        .package-features li:last-child { border-bottom: none; }
        .feature-icon { color: #48bb78; font-weight: bold; }
        
        .package-stats { background: #f7fafc; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; }
        .stat-row { display: flex; justify-content: space-between; margin-bottom: 0.5rem; }
        .stat-label { color: #718096; }
        .stat-value { font-weight: 600; }
        
        .package-actions { display: grid; gap: 0.75rem; }
        
        .btn { padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; text-decoration: none; text-align: center; transition: all 0.2s; border: none; cursor: pointer; }
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .btn-success { background: #48bb78; color: white; }
        .btn-warning { background: #ed8936; color: white; }
        .btn-danger { background: #e53e3e; color: white; }
        .btn-secondary { background: #edf2f7; color: #4a5568; }
        .btn:hover { transform: translateY(-1px); }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal-content { background: white; margin: 5% auto; padding: 2rem; border-radius: 12px; max-width: 600px; position: relative; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .modal-title { font-size: 1.3rem; font-weight: 600; }
        .close { font-size: 1.5rem; cursor: pointer; color: #a0aec0; }
        .close:hover { color: #2d3748; }
        
        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #2d3748; }
        .form-input { width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px; }
        .form-textarea { width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px; min-height: 100px; resize: vertical; }
        .form-select { width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px; }
        
        .feature-builder { border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; margin-top: 1rem; }
        .feature-item { display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center; }
        .feature-item:last-child { margin-bottom: 0; }
        
        @media (max-width: 768px) {
            .packages-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="logo">Webimvar Enterprise</div>
            <a href="panel.php" class="btn btn-secondary">Dashboard'a Dön</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Paket Yönetimi</h1>
            <button class="btn btn-primary" onclick="openPackageModal()">Yeni Paket Oluştur</button>
        </div>

        <div class="packages-grid" id="packagesGrid">
            <!-- Paketler buraya yüklenecek -->
        </div>

        <!-- Package Modal -->
        <div id="packageModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title" id="modalTitle">Yeni Paket</h3>
                    <span class="close" onclick="closePackageModal()">&times;</span>
                </div>
                
                <form id="packageForm">
                    <input type="hidden" id="packageId" value="">
                    
                    <div class="form-group">
                        <label class="form-label">Paket Adı</label>
                        <input type="text" id="packageName" class="form-input" required>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Fiyat (₺)</label>
                            <input type="number" id="packagePrice" class="form-input" step="0.01" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Faturalandırma</label>
                            <select id="billingCycle" class="form-select">
                                <option value="monthly">Aylık</option>
                                <option value="yearly">Yıllık</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Özellikler</label>
                        <div class="feature-builder">
                            <div class="feature-item">
                                <input type="text" placeholder="Galeri Görseli" class="form-input" style="flex: 1;">
                                <input type="number" placeholder="5" class="form-input" style="width: 100px;">
                                <button type="button" class="btn btn-danger" onclick="removeFeature(this)" style="padding: 0.5rem;">×</button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="addFeature()" style="margin-top: 1rem;">Özellik Ekle</button>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Maksimum Site</label>
                            <input type="number" id="maxSites" class="form-input" value="1">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Depolama (MB)</label>
                            <input type="number" id="storageLimit" class="form-input" value="100">
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                        <button type="button" class="btn btn-secondary" onclick="closePackageModal()">İptal</button>
                        <button type="submit" class="btn btn-success">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let packages = [];
        
        async function loadPackages() {
            try {
                const response = await fetch('api/admin-packages.php');
                const data = await response.json();
                
                if (data.success) {
                    packages = data.packages;
                    renderPackages();
                }
            } catch (error) {
                console.error('Paket yükleme hatası:', error);
            }
        }
        
        function renderPackages() {
            const grid = document.getElementById('packagesGrid');
            grid.innerHTML = packages.map(pkg => `
                <div class="package-card">
                    <div class="package-header ${pkg.slug}">
                        <div class="package-name">${pkg.name}</div>
                        <div class="package-price">₺${pkg.price}</div>
                        <div class="package-period">${pkg.billing_cycle === 'monthly' ? 'aylık' : 'yıllık'}</div>
                    </div>
                    
                    <div class="package-body">
                        <div class="package-stats">
                            <div class="stat-row">
                                <span class="stat-label">Aktif Kullanıcı:</span>
                                <span class="stat-value">${pkg.user_count || 0}</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">Aylık Gelir:</span>
                                <span class="stat-value">₺${((pkg.user_count || 0) * pkg.price).toLocaleString('tr-TR')}</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">Durum:</span>
                                <span class="stat-value" style="color: ${pkg.is_active ? '#48bb78' : '#e53e3e'}">
                                    ${pkg.is_active ? 'Aktif' : 'Pasif'}
                                </span>
                            </div>
                        </div>
                        
                        <ul class="package-features">
                            ${Object.entries(JSON.parse(pkg.features || '{}')).map(([key, value]) => `
                                <li>
                                    <span class="feature-icon">✓</span>
                                    ${key}: ${value}
                                </li>
                            `).join('')}
                        </ul>
                        
                        <div class="package-actions">
                            <button class="btn btn-primary" onclick="editPackage(${pkg.id})">Düzenle</button>
                            <button class="btn ${pkg.is_active ? 'btn-warning' : 'btn-success'}" 
                                    onclick="togglePackageStatus(${pkg.id}, ${!pkg.is_active})">
                                ${pkg.is_active ? 'Pasif Yap' : 'Aktif Yap'}
                            </button>
                            <button class="btn btn-secondary" onclick="viewPackageUsers(${pkg.id})">
                                Kullanıcıları Görüntüle (${pkg.user_count || 0})
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');
        }
        
        function openPackageModal(packageId = null) {
            const modal = document.getElementById('packageModal');
            const form = document.getElementById('packageForm');
            const title = document.getElementById('modalTitle');
            
            if (packageId) {
                const pkg = packages.find(p => p.id === packageId);
                title.textContent = 'Paket Düzenle';
                document.getElementById('packageId').value = pkg.id;
                document.getElementById('packageName').value = pkg.name;
                document.getElementById('packagePrice').value = pkg.price;
                document.getElementById('billingCycle').value = pkg.billing_cycle;
                document.getElementById('maxSites').value = JSON.parse(pkg.limits_json || '{}').sites || 1;
                document.getElementById('storageLimit').value = JSON.parse(pkg.limits_json || '{}').storage_mb || 100;
            } else {
                title.textContent = 'Yeni Paket';
                form.reset();
                document.getElementById('packageId').value = '';
            }
            
            modal.style.display = 'block';
        }
        
        function closePackageModal() {
            document.getElementById('packageModal').style.display = 'none';
        }
        
        function editPackage(packageId) {
            openPackageModal(packageId);
        }
        
        async function togglePackageStatus(packageId, newStatus) {
            try {
                const response = await fetch('api/admin-package-status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ package_id: packageId, is_active: newStatus })
                });
                
                const data = await response.json();
                if (data.success) {
                    loadPackages();
                } else {
                    alert('Hata: ' + data.error);
                }
            } catch (error) {
                alert('Bağlantı hatası');
            }
        }
        
        function addFeature() {
            const builder = document.querySelector('.feature-builder');
            const newFeature = document.createElement('div');
            newFeature.className = 'feature-item';
            newFeature.innerHTML = `
                <input type="text" placeholder="Özellik adı" class="form-input" style="flex: 1;">
                <input type="text" placeholder="Değer" class="form-input" style="width: 100px;">
                <button type="button" class="btn btn-danger" onclick="removeFeature(this)" style="padding: 0.5rem;">×</button>
            `;
            builder.appendChild(newFeature);
        }
        
        function removeFeature(button) {
            button.parentElement.remove();
        }
        
        function viewPackageUsers(packageId) {
            window.open(`package-users.php?package_id=${packageId}`, '_blank');
        }
        
        // Form submission
        document.getElementById('packageForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = {
                id: document.getElementById('packageId').value,
                name: document.getElementById('packageName').value,
                price: document.getElementById('packagePrice').value,
                billing_cycle: document.getElementById('billingCycle').value,
                max_sites: document.getElementById('maxSites').value,
                storage_limit: document.getElementById('storageLimit').value
            };
            
            try {
                const response = await fetch('api/admin-save-package.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                if (data.success) {
                    closePackageModal();
                    loadPackages();
                } else {
                    alert('Hata: ' + data.error);
                }
            } catch (error) {
                alert('Kaydetme hatası');
            }
        });
        
        // Sayfa yüklendiğinde paketleri yükle
        document.addEventListener('DOMContentLoaded', loadPackages);
        
        // Modal dışına tıklanınca kapat
        window.onclick = function(event) {
            const modal = document.getElementById('packageModal');
            if (event.target === modal) {
                closePackageModal();
            }
        }
    </script>
</body>
</html>