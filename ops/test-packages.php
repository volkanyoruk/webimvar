<?php
define('WEBIMVAR_ENTERPRISE', true);
require __DIR__ . '/../core/config.php';
require __DIR__ . '/../core/classes/Database.php';

try {
    $db = Database::getInstance();
    $packages = $db->query("SELECT * FROM packages ORDER BY sort_order ASC");
    
    echo "<h2>SaaS Paket Sistemi Test</h2>";
    echo "<p>Toplam " . count($packages) . " paket bulundu:</p>";
    
    foreach ($packages as $pkg) {
        echo "<div style='border:1px solid #ddd; margin:10px; padding:15px; border-radius:8px;'>";
        echo "<h3>" . htmlspecialchars($pkg['name']) . "</h3>";
        echo "<p><strong>Fiyat:</strong> " . $pkg['price_monthly'] . " ₺/ay";
        if ($pkg['price_yearly']) {
            echo " | " . $pkg['price_yearly'] . " ₺/yıl";
        }
        echo "</p>";
        echo "<p><strong>Durum:</strong> " . ($pkg['is_active'] ? 'Aktif' : 'Pasif');
        echo " | <strong>Öne çıkan:</strong> " . ($pkg['is_featured'] ? 'Evet' : 'Hayır') . "</p>";
        echo "<p>" . htmlspecialchars($pkg['description']) . "</p>";
        
        if ($pkg['features']) {
            $features = json_decode($pkg['features'], true);
            echo "<p><strong>Özellikler:</strong> " . implode(', ', array_keys(array_filter($features))) . "</p>";
        }
        
        if ($pkg['limits']) {
            $limits = json_decode($pkg['limits'], true);
            echo "<p><strong>Limitler:</strong> " . $limits['disk_space_gb'] . "GB disk, " . $limits['bandwidth_gb'] . "GB bandwidth</p>";
        }
        echo "</div>";
    }
    
    echo "<hr><p style='color: green;'><strong>Database bağlantısı başarılı! Paket yönetimi sistemi hazır.</strong></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Hata: " . $e->getMessage() . "</p>";
}
?>