<?php
// Database yapısını kontrol et
require_once '../config/database.php';

echo "<h2>🔍 Database Yapısı Kontrol</h2>";

try {
    // Mevcut tabloları listele
    echo "<h3>📋 Mevcut Tablolar:</h3>";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach($tables as $table) {
        echo "✅ " . $table . "<br>";
    }
    
    echo "<hr>";
    
    // Eğer users tablosu varsa yapısını göster
    if(in_array('users', $tables)) {
        echo "<h3>👥 USERS Tablosu Mevcut - Yapısı:</h3>";
        $columns = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_ASSOC);
        foreach($columns as $column) {
            echo "🔹 " . $column['Field'] . " (" . $column['Type'] . ")<br>";
        }
    } else {
        echo "<h3>❌ USERS tablosu YOK - Oluşturulması gerekiyor</h3>";
    }
    
    echo "<hr>";
    
    // Packages tablosunu da kontrol et
    if(in_array('packages', $tables)) {
        echo "<h3>📦 PACKAGES Tablosu Mevcut - Yapısı:</h3>";
        $packages_columns = $pdo->query("DESCRIBE packages")->fetchAll(PDO::FETCH_ASSOC);
        foreach($packages_columns as $column) {
            echo "🔹 " . $column['Field'] . " (" . $column['Type'] . ")<br>";
        }
        
        // Kayıt sayısını göster
        $count = $pdo->query("SELECT COUNT(*) FROM packages")->fetchColumn();
        echo "<br>📊 Toplam paket sayısı: " . $count;
    }
    
} catch(Exception $e) {
    echo "❌ Hata: " . $e->getMessage();
}
?>