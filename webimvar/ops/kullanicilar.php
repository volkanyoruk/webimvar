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
    <title>Kullanıcı Yönetimi - Ops Panel</title>
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
        .users-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .users-table th, .users-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .users-table th { background: #f8f9fa; font-weight: 600; }
        .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: bold; }
        .status-active { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-suspended { background: #f8d7da; color: #721c24; }
        .btn { padding: 6px 12px; background: #2c3e50; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.8rem; }
        .btn-sm { padding: 4px 8px; font-size: 0.7rem; }
        .btn-success { background: #27ae60; }
        .btn-warning { background: #f39c12; }
        .btn-danger { background: #e74c3c; }
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 2rem; }
        .page-btn { padding: 8px 12px; border: 1px solid #ddd; background: white; cursor: pointer; }
        .page-btn.active { background: #2c3e50; color: white; }
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
                <a href="kullanicilar.php" class="menu-item active">👥 Kullanıcılar</a>
                <a href="siteler.php" class="menu-item">🌐 Siteler</a>
                <a href="raporlar.php" class="menu-item">📈 Raporlar</a>
            </div>

            <div class="main-content">
                <h2>Kullanıcı Yönetimi</h2>
                
                <input type="text" class="search-box" placeholder="Kullanıcı ara..." id="searchBox">
                
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Email</th>
                            <th>Ad Soyad</th>
                            <th>Meslek</th>
                            <th>Durum</th>
                            <th>Site</th>
                            <th>Kayıt Tarihi</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody id="usersTable">
                        <tr><td colspan="8" style="text-align:center;">Yükleniyor...</td></tr>
                    </tbody>
                </table>

                <div class="pagination" id="pagination"></div>
            </div>
        </div>
    </div>

    <script>
        let currentPage = 1;
        let searchTerm = '';

        async function loadUsers(page = 1, search = '') {
            try {
                const response = await fetch(`api/admin-users.php?page=${page}&search=${search}`);
                const data = await response.json();
                
                if (data.success) {
                    const tbody = document.getElementById('usersTable');
                    tbody.innerHTML = data.users.map(user => `
                        <tr>
                            <td>${user.id}</td>
                            <td>${user.email}</td>
                            <td>${user.name || '-'}</td>
                            <td>${user.profession || '-'}</td>
                            <td><span class="status-badge status-${user.status}">${getStatusText(user.status)}</span></td>
                            <td>
                                ${user.subdomain ? 
                                    `<a href="https://${user.subdomain}.webimvar.com" target="_blank">${user.subdomain}</a>` : 
                                    '-'
                                }
                            </td>
                            <td>${formatDate(user.created_at)}</td>
                            <td>
                                <select onchange="changeUserStatus(${user.id}, this.value)" class="btn btn-sm">
                                    <option value="active" ${user.status === 'active' ? 'selected' : ''}>Aktif</option>
                                    <option value="suspended" ${user.status === 'suspended' ? 'selected' : ''}>Askıya Al</option>
                                </select>
                                <button class="btn btn-sm btn-success" onclick="viewUser(${user.id})">Görüntüle</button>
                            </td>
                        </tr>
                    `).join('');
                    
                    updatePagination(data.pagination);
                }
            } catch (error) {
                document.getElementById('usersTable').innerHTML = '<tr><td colspan="8">Yükleme hatası</td></tr>';
            }
        }

        function getStatusText(status) {
            const statusMap = {
                'active': 'Aktif',
                'pending': 'Bekliyor',
                'suspended': 'Askıda'
            };
            return statusMap[status] || status;
        }

        function formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('tr-TR');
        }

        async function changeUserStatus(userId, newStatus) {
            try {
                const response = await fetch('api/admin-user-status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: userId, status: newStatus })
                });
                
                const data = await response.json();
                if (data.success) {
                    loadUsers(currentPage, searchTerm);
                } else {
                    alert('Hata: ' + data.error);
                }
            } catch (error) {
                alert('Bağlantı hatası');
            }
        }

        function viewUser(userId) {
            window.open(`user-detail.php?id=${userId}`, '_blank');
        }

        function updatePagination(pagination) {
            const paginationDiv = document.getElementById('pagination');
            let html = '';
            
            for (let i = 1; i <= pagination.totalPages; i++) {
                html += `<button class="page-btn ${i === pagination.currentPage ? 'active' : ''}" 
                        onclick="loadUsers(${i}, searchTerm); currentPage = ${i};">${i}</button>`;
            }
            
            paginationDiv.innerHTML = html;
        }

        document.getElementById('searchBox').addEventListener('input', (e) => {
            searchTerm = e.target.value;
            currentPage = 1;
            setTimeout(() => loadUsers(1, searchTerm), 300);
        });

        window.addEventListener('DOMContentLoaded', () => loadUsers());
    </script>
</body>
</html>