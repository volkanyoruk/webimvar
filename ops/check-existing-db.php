<?php
// Hata göstermeyi aç
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>🔍 Mevcut Database Dosyasını Kontrol</h2>";

// Mevcut database dosyasının yolu
$db_path = '../core/classes/Database.php';

echo "<h3>📂 Dosya Kontrol:</h3>";
if(file_exists($db_path)) {
    echo "✅ {$db_path} mevcut!<br>";
    echo "Dosya boyutu: " . filesize($db_path) . " byte<br>";
    
    echo "<h3>📖 Dosya İçeriği (İlk 500 karakter):</h3>";
    echo "<pre style='background:#f5f5f5; padding:10px; border-radius:5px;'>";
    echo htmlspecialchars(substr(file_get_contents($db_path), 0, 500));
    echo "</pre>";
    
    echo "<h3>🔗 Database Bağlantısı Test:</h3>";
    try {
        require_once $db_path;
        echo "✅ Database dosyası başarıyla yüklendi!<br>";
        
        // Hangi değişkenler tanımlı kontrol et
        if(isset($pdo)) {
            echo "✅ \$pdo değişkeni mevcut<br>";
            
            // Database test
            $result = $pdo->query("SELECT 1 as test")->fetch();
            if($result['test'] == 1) {
                echo "✅ Database bağlantısı ÇALIŞIYOR!<br>";
                
                echo "<h3>📊 Mevcut Tablolar:</h3>";
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                foreach($tables as $table) {
                    echo "📦 " . $table . "<br>";
                }
            }
        } elseif(isset($db) || isset($conn) || isset($connection)) {
            echo "✅ Başka database değişkeni bulundu<br>";
            echo "Mevcut değişkenler:<br>";
            $vars = get_defined_vars();
            foreach($vars as $key => $value) {
                if(is_object($value)) {
                    echo "🔹 \${$key} (" . get_class($value) . ")<br>";
                }
            }
        } else {
            echo "❌ Database bağlantı değişkeni bulunamadı<br>";
        }
        
    } catch(Exception $e) {
        echo "❌ Hata: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ {$db_path} bulunamadı<br>";
    
    echo "<h3>📁 core/classes klasöründeki dosyalar:</h3>";
    $core_path = '../core/classes/';
    if(is_dir($core_path)) {
        $files = scandir($core_path);
        foreach($files as $file) {
            if($file != '.' && $file != '..') {
                echo "📄 " . $file . "<br>";
            }
        }
    } else {
        echo "❌ core/classes klasörü bulunamadı<br>";
    }
}

echo "<hr>";
echo "<h3>🎯 Sonuç:</h3>";
echo "Bu test sonucuna göre mevcut database dosyanızı users.php'de kullanacağız.";
?>