<!-- Kullanıcı Yönetimi - Ana Sayfa -->
<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Kullanıcı Yönetimi</h1>
        <p class="text-gray-600 mt-1">Sistem kullanıcılarını görüntüle ve yönet</p>
    </div>
    <button class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition" onclick="showAddUserModal()">
        + Yeni Kullanıcı
    </button>
</div>

<!-- İstatistik Kartları -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="flex items-center">
            <div class="p-2 bg-blue-100 rounded-lg">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Toplam</p>
                <p class="text-2xl font-semibold text-gray-900"><?= $stats['total'] ?? 0 ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="flex items-center">
            <div class="p-2 bg-green-100 rounded-lg">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Aktif</p>
                <p class="text-2xl font-semibold text-green-600"><?= $stats['active'] ?? 0 ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="flex items-center">
            <div class="p-2 bg-red-100 rounded-lg">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Pasif</p>
                <p class="text-2xl font-semibold text-red-600"><?= $stats['inactive'] ?? 0 ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="flex items-center">
            <div class="p-2 bg-purple-100 rounded-lg">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Bu Ay</p>
                <p class="text-2xl font-semibold text-purple-600"><?= $stats['this_month'] ?? 0 ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="flex items-center">
            <div class="p-2 bg-yellow-100 rounded-lg">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Bugün</p>
                <p class="text-2xl font-semibold text-yellow-600"><?= $stats['today'] ?? 0 ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="flex items-center">
            <div class="p-2 bg-indigo-100 rounded-lg">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">7 Gün Aktif</p>
                <p class="text-2xl font-semibold text-indigo-600"><?= $stats['active_last_week'] ?? 0 ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Flash Mesajları -->
<?php if ($msg = Session::getFlash('success')): ?>
    <div class="mb-6 p-4 bg-green-100 text-green-800 rounded-lg border border-green-200">
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
            </svg>
            <?= htmlspecialchars($msg) ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($error = Session::getFlash('error')): ?>
    <div class="mb-6 p-4 bg-red-100 text-red-800 rounded-lg border border-red-200">
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
        </svg>
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- Arama ve Filtreler -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
    <form method="GET" action="/ops/users.php" class="flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-2">Arama</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search ?? '') ?>" 
                   placeholder="E-posta veya ad ile ara..." 
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
        
        <div class="min-w-[150px]">
            <label class="block text-sm font-medium text-gray-700 mb-2">Durum</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Tüm Kullanıcılar</option>
                <option value="active" <?= ($status ?? '') === 'active' ? 'selected' : '' ?>>Aktif</option>
                <option value="inactive" <?= ($status ?? '') === 'inactive' ? 'selected' : '' ?>>Pasif</option>
            </select>
        </div>
        
        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                🔍 Ara
            </button>
            <a href="/ops/users.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
                🔄 Temizle
            </a>
        </div>
    </form>
</div>

<!-- Kullanıcı Tablosu -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
        <h3 class="text-lg font-medium text-gray-900">
            Kullanıcılar 
            <span class="text-sm text-gray-500 ml-2">(<?= $totalUsers ?? 0 ?> toplam)</span>
        </h3>
    </div>

    <?php if (!empty($users)): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kullanıcı</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">E-posta</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rol</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Durum</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Site Sayısı</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kayıt Tarihi</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($users as $user): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 font-medium">
                                            <?= strtoupper(substr($user['full_name'] ?? $user['email'], 0, 1)) ?>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($user['full_name'] ?? 'Ad girilmemiş') ?>
                                        </div>
                                        <div class="text-sm text-gray-500">ID: <?= $user['id'] ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?= htmlspecialchars($user['email']) ?></div>
                                <?php if ($user['last_login']): ?>
                                    <div class="text-xs text-gray-500">
                                        Son giriş: <?= date('d.m.Y H:i', strtotime($user['last_login'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
                                    <?= htmlspecialchars($user['role'] ?? 'user') ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?= $user['is_active'] ? '✅ Aktif' : '❌ Pasif' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    🌐 <?= $user['site_count'] ?? 0 ?> site
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= date('d.m.Y', strtotime($user['created_at'])) ?>
                                <div class="text-xs text-gray-400">
                                    <?= $user['login_count'] ? $user['login_count'] . ' giriş' : 'Hiç giriş yapmadı' ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <a href="/ops/users/view.php?id=<?= $user['id'] ?>" 
                                       class="text-blue-600 hover:text-blue-900 transition">
                                        👁️ Görüntüle
                                    </a>
                                    <button onclick="toggleUserStatus(<?= $user['id'] ?>, <?= $user['is_active'] ? 0 : 1 ?>)"
                                            class="<?= $user['is_active'] ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900' ?> transition">
                                        <?= $user['is_active'] ? '🚫 Pasif Yap' : '✅ Aktif Yap' ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Sayfalama -->
        <?php if ($totalPages > 1): ?>
            <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Önceki
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
                           class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Sonraki
                        </a>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Toplam <span class="font-medium"><?= $totalUsers ?></span> kullanıcının 
                            <span class="font-medium"><?= (($page - 1) * 20) + 1 ?></span> - 
                            <span class="font-medium"><?= min($page * 20, $totalUsers) ?></span> arası gösteriliyor
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                                   class="relative inline-flex items-center px-4 py-2 border text-sm font-medium
                                          <?= $i === $page ? 
                                              'z-10 bg-blue-50 border-blue-500 text-blue-600' : 
                                              'bg-white border-gray-300 text-gray-500 hover:bg-gray-50' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                        </nav>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Boş Durum -->
        <div class="text-center py-16">
            <div class="text-gray-400 mb-4">
                <svg class="w-20 h-20 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" 
                          d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                </svg>
            </div>
            <h3 class="text-xl font-medium text-gray-900 mb-2">
                <?= !empty($search) ? 'Arama sonucu bulunamadı' : 'Henüz kullanıcı yok' ?>
            </h3>
            <p class="text-gray-600 mb-6">
                <?= !empty($search) ? 
                    'Farklı anahtar kelimelerle tekrar deneyin.' : 
                    'İlk kullanıcıyı ekleyerek başlayabilirsiniz.' ?>
            </p>
            <?php if (empty($search)): ?>
                <button onclick="showAddUserModal()" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
                    İlk Kullanıcıyı Ekle
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Kullanıcı durumu değiştirme
async function toggleUserStatus(userId, newStatus) {
    if (!confirm('Kullanıcının durumunu değiştirmek istediğinizden emin misiniz?')) {
        return;
    }

    try {
        const response = await fetch('/ops/users/change-status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `user_id=${userId}&is_active=${newStatus}&csrf_token=<?= htmlspecialchars($csrf_token) ?>`
        });

        const data = await response.json();
        
        if (data.success) {
            location.reload(); // Sayfayı yenile
        } else {
            alert('Hata: ' + data.error);
        }
    } catch (error) {
        alert('Bağlantı hatası');
    }
}

// Yeni kullanıcı modalı (placeholder)
function showAddUserModal() {
    alert('Yeni kullanıcı ekleme özelliği yakında aktif olacak!');
}
</script>