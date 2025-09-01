<!-- Paket Yönetimi - Ana Sayfa -->
<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">SaaS Paket Yönetimi</h1>
        <p class="text-gray-600 mt-1">Hosting paketlerini yönet, düzenle ve takip et</p>
    </div>
    <a href="/ops/packages/create.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
        + Yeni Paket Oluştur
    </a>
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
    </div>
<?php endif; ?>

<?php if (!empty($packages)): ?>
    <!-- Paket Kartları -->
    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
        <?php foreach ($packages as $package): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition">
                <!-- Paket Header -->
                <div class="p-6 border-b border-gray-100">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($package['name']) ?></h3>
                            <?php if ($package['display_name'] && $package['display_name'] !== $package['name']): ?>
                                <p class="text-sm text-gray-500"><?= htmlspecialchars($package['display_name']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="flex space-x-1">
                            <?php if ($package['is_featured']): ?>
                                <span class="inline-block bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full font-medium">
                                    ⭐ Öne Çıkan
                                </span>
                            <?php endif; ?>
                            <span class="inline-block <?= $package['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> text-xs px-2 py-1 rounded-full font-medium">
                                <?= $package['is_active'] ? '✓ Aktif' : '✗ Pasif' ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Fiyat -->
                    <div class="flex items-baseline space-x-2">
                        <span class="text-3xl font-bold text-blue-600"><?= number_format($package['price_monthly'], 0, ',', '.') ?> ₺</span>
                        <span class="text-gray-500">/ay</span>
                        <?php if ($package['price_yearly']): ?>
                            <span class="text-sm text-green-600 bg-green-50 px-2 py-1 rounded">
                                <?= number_format($package['price_yearly'], 0, ',', '.') ?> ₺/yıl
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Paket İçeriği -->
                <div class="p-6">
                    <p class="text-gray-600 text-sm mb-4"><?= htmlspecialchars($package['description']) ?></p>

                    <!-- Limitler -->
                    <div class="space-y-2 mb-4">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Disk Alanı:</span>
                            <span class="font-medium"><?= round($package['disk_quota'] / 1024, 1) ?> GB</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Bandwidth:</span>
                            <span class="font-medium"><?= round($package['bandwidth_quota'] / 1024, 1) ?> GB</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Site Sayısı:</span>
                            <span class="font-medium"><?= $package['sites_limit'] ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">E-posta:</span>
                            <span class="font-medium"><?= $package['email_accounts_limit'] ?: 'Sınırsız' ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Database:</span>
                            <span class="font-medium"><?= $package['databases_limit'] ?></span>
                        </div>
                    </div>

                    <!-- Özellikler -->
                    <?php if (!empty($package['features'])): ?>
                        <div class="space-y-1 mb-4">
                            <h5 class="text-xs font-medium text-gray-700 mb-2">ÖZELLİKLER</h5>
                            <?php foreach ($package['features'] as $feature => $enabled): ?>
                                <?php if ($enabled): ?>
                                    <div class="flex items-center text-xs text-green-600">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        <?php
                                        $featureNames = [
                                            'ssl_certificate' => 'SSL Sertifikası',
                                            'email_support' => 'E-posta Desteği',
                                            'backup_daily' => 'Günlük Yedekleme',
                                            'cdn' => 'CDN Desteği',
                                            'priority_support' => 'Öncelikli Destek'
                                        ];
                                        echo $featureNames[$feature] ?? ucfirst(str_replace('_', ' ', $feature));
                                        ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Paket Aksiyonları -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 rounded-b-xl">
                    <div class="flex space-x-2">
                        <a href="/ops/packages/edit.php?id=<?= $package['id'] ?>" 
                           class="flex-1 text-center bg-blue-600 text-white py-2 px-3 rounded text-sm font-medium hover:bg-blue-700 transition">
                            Düzenle
                        </a>
                        <form method="post" action="/ops/packages/delete.php" 
                              onsubmit="return confirm('<?= htmlspecialchars($package['name']) ?> paketini silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')" class="flex-1">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="id" value="<?= $package['id'] ?>">
                            <button class="w-full bg-red-600 text-white py-2 px-3 rounded text-sm font-medium hover:bg-red-700 transition">
                                Sil
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?php else: ?>
    <!-- Boş Durum -->
    <div class="text-center py-16">
        <div class="text-gray-400 mb-4">
            <svg class="w-20 h-20 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
            </svg>
        </div>
        <h3 class="text-xl font-medium text-gray-900 mb-2">Henüz paket yok</h3>
        <p class="text-gray-600 mb-6 max-w-md mx-auto">
            SaaS platformunuz için ilk hosting paketinizi oluşturun. Müşterileriniz bu paketler arasından seçim yapabilecek.
        </p>
        <a href="/ops/packages/create.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
            İlk Paketinizi Oluşturun
        </a>
    </div>
<?php endif; ?>

<!-- İstatistik Kartları -->
<?php if (!empty($packages)): ?>
    <div class="mt-12 grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-lg border border-gray-200">
            <div class="text-sm text-gray-600">Toplam Paket</div>
            <div class="text-2xl font-bold text-blue-600"><?= count($packages) ?></div>
        </div>
        <div class="bg-white p-4 rounded-lg border border-gray-200">
            <div class="text-sm text-gray-600">Aktif Paket</div>
            <div class="text-2xl font-bold text-green-600"><?= count(array_filter($packages, fn($p) => $p['is_active'])) ?></div>
        </div>
        <div class="bg-white p-4 rounded-lg border border-gray-200">
            <div class="text-sm text-gray-600">Öne Çıkan</div>
            <div class="text-2xl font-bold text-yellow-600"><?= count(array_filter($packages, fn($p) => $p['is_featured'])) ?></div>
        </div>
        <div class="bg-white p-4 rounded-lg border border-gray-200">
            <div class="text-sm text-gray-600">Ort. Fiyat</div>
            <div class="text-2xl font-bold text-purple-600">
                <?php
                $prices = array_column(array_filter($packages, fn($p) => $p['is_active']), 'price_monthly');
                echo !empty($prices) ? number_format(array_sum($prices) / count($prices), 0) . ' ₺' : '0 ₺';
                ?>
            </div>
        </div>
    </div>
<?php endif; ?>