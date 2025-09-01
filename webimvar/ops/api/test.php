<?php
echo "API klasörü çalışıyor!<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Current Dir: " . __DIR__ . "<br>";

// Config dosyası var mı test et
$configPath = '../../api/config.php';
if (file_exists($configPath)) {
    echo "Config dosyası bulundu: $configPath<br>";
} else {
    echo "Config dosyası bulunamadı: $configPath<br>";
}
?>