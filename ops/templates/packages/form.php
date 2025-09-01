<!-- Paket Form - Oluştur/Düzenle -->
<div class="max-w-4xl mx-auto">
    <!-- Breadcrumb -->
    <div class="flex items-center mb-6">
        <a href="/ops/packages.php" class="text-blue-600 hover:text-blue-700 mr-4">
            ← Paketlere Geri Dön
        </a>
        <h1 class="text-2xl font-bold text-gray-900"><?= $isEdit ? 'Paket Düzenle' : 'Yeni Paket Oluştur' ?></h1>
    </div>

    <!-- Flash Mesajları -->
    <?php if ($error = Session::getFlash('error')): ?>
        <div class="mb-6 p-4 bg-red-100 text-red-800 rounded-lg border border-red-200">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="post" action="<?= $isEdit ? '/ops/packages/update.php' : '/ops/packages/store.php' ?>" class="bg-white rounded-xl shadow-sm border border-gray-200">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= $package['id'] ?>">
        <?php endif; ?>

        <div class="p-6 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">Paket Bilgileri</h2>
            <p class="text-gray-600 mt-1">Paketinizin temel bilgilerini girin</p>
        </div>

        <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Sol Kolon - Temel Bilgiler -->
            <div class="space-y-6">
                <h3 class="text-md font-medium text-gray-900 border-b pb-2">Temel Bilgiler</h3>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Paket Adı <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" required value="<?= htmlspecialchars($package['name']) ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Örn: Profesyonel Hosting">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Görünen Ad</label>
                    <input type="text" name="display_name" value="<?= htmlspecialchars($package['display_name'] ?? '') ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Müşterilerin göreceği ad (opsiyonel)">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Açıklama</label>
                    <textarea name="description" rows="3" 
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Paket hakkında kısa açıklama"><?= htmlspecialchars($package['description']) ?></textarea>
                </div>

                <!-- Fiyatlandırma -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Aylık Fiyat (₺) <span class="text-red-500">*</span>
                        </label>
                        <input type="number" name="price_monthly" required step="0.01" min="0"
                               value="<?= $package['price_monthly'] ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Yıllık Fiyat (₺)</label>
                        <input type="number" name="price_yearly" step="0.01" min="0"
                               value="<?= $package['price_yearly'] ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="İndirimli yıllık fiyat">
                    </div>
                </div>

                <!-- Sıralama ve Durum -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sıralama</label>
                        <input type="number" name="sort_order" min="0" value="<?= $package['sort_order'] ?? 0 ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Küçük sayı önce gösterilir</p>
                    </div>
                    <div class="flex flex-col justify-end">
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_active" <?= $package['is_active'] ? 'checked' : '' ?>
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2">
                                <span class="text-sm font-medium text-gray-700">Aktif</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="is_featured" <?= $package['is_featured'] ? 'checked' : '' ?>
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2">
                                <span class="text-sm font-medium text-gray-700">Öne Çıkan</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sağ Kolon - Limitler ve Özellikler -->
            <div class="space-y-6">
                <h3 class="text-md font-medium text-gray-900 border-b pb-2">Limitler</h3>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Disk Alanı (MB)</label>
                        <input type="number" name="disk_quota" min="0" 
                               value="<?= $package['disk_quota'] ?? 0 ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-xs text-gray-500 mt-1">1024 MB = 1 GB</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Bandwidth (MB)</label>
                        <input type="number" name="bandwidth_quota" min="0" 
                               value="<?= $package['bandwidth_quota'] ?? 0 ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Site Sayısı</label>
                        <input type="number" name="sites_limit" min="1" 
                               value="<?= $package['sites_limit'] ?? 1 ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Database</label>
                        <input type="number" name="databases_limit" min="0" 
                               value="<?= $package['databases_limit'] ?? 1 ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">E-posta</label>
                        <input type="number" name="email_accounts_limit" min="0" 
                               value="<?= $package['email_accounts_limit'] ?? 0 ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-xs text-gray-500 mt-1">0 = Sınırsız</p>
                    </div>
                </div>

                <!-- Özellikler -->
                <h3 class="text-md font-medium text-gray-900 border-b pb-2 mt-8">Özellikler</h3>
                
                <div class="grid grid-cols-1 gap-3">
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                        <input type="checkbox" name="feature_ssl" 
                               <?= ($package['features']['ssl_certificate'] ?? false) ? 'checked' : '' ?>
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-3">
                        <div>
                            <div class="text-sm font-medium text-gray-700">SSL Sertifikası</div>
                            <div class="text-xs text-gray-500">Ücretsiz Let's Encrypt SSL</div>
                        </div>
                    </label>
                    
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                        <input type="checkbox" name="feature_email_support"
                               <?= ($package['features']['email_support'] ?? false) ? 'checked' : '' ?>
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-3">
                        <div>
                            <div class="text-sm font-medium text-gray-700">E-posta Desteği</div>
                            <div class="text-xs text-gray-500">24/7 e-posta destek hizmeti</div>
                        </div>
                    </label>
                    
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                        <input type="checkbox" name="feature_backup"
                               <?= ($package['features']['backup_daily'] ?? false) ? 'checked' : '' ?>
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-3">
                        <div>
                            <div class="text-sm font-medium text-gray-700">Günlük Yedekleme</div>
                            <div class="text-xs text-gray-500">Otomatik günlük backup</div>
                        </div>
                    </label>
                    
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                        <input type="checkbox" name="feature_cdn"
                               <?= ($package['features']['cdn'] ?? false) ? 'checked' : '' ?>
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-3">
                        <div>
                            <div class="text-sm font-medium text-gray-700">CDN Desteği</div>
                            <div class="text-xs text-gray-500">Hızlandırma ve cache</div>
                        </div>
                    </label>
                    
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                        <input type="checkbox" name="feature_priority_support"
                               <?= ($package['features']['priority_support'] ?? false) ? 'checked' : '' ?>
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-3">
                        <div>
                            <div class="text-sm font-medium text-gray-700">Öncelikli Destek</div>
                            <div class="text-xs text-gray-500">Hızlı yanıt garantisi</div>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <!-- Form Footer -->
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 rounded-b-xl">
            <div class="flex justify-end space-x-3">
                <a href="/ops/packages.php" class="px-6 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    İptal
                </a>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <?= $isEdit ? 'Güncelle' : 'Oluştur' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Form Yardımcısı -->
<div class="max-w-4xl mx-auto mt-6">
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <h4 class="text-sm font-medium text-blue-900 mb-2">💡 İpuçları</h4>
        <ul class="text-sm text-blue-800 space-y-1">
            <li>• Disk alanını MB cinsinden girin (1024 MB = 1 GB)</li>
            <li>• E-posta limiti 0 ise sınırsız kabul edilir</li>
            <li>• Öne çıkan paketler ana sayfada vurgulanır</li>
            <li>• Yıllık fiyat aylık fiyattan düşük ise otomatik indirim hesaplanır</li>
        </ul>
    </div>
</div>