<?php
// Hata göstermeyi aç
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>🔧 Basit Debug</h2>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Çalışma Dizini: " . getcwd() . "<br>";
echo "<br>";

// Database config dosyasını kontrol et
echo "<h3>📂 Dosya Kontrolü:</h3>";

$config_path = '../config/database.php';
if(file_exists($config_path)) {
    echo "✅ {$config_path} mevcut<br>";
    echo "Dosya boyutu: " . filesize($config_path) . " byte<br>";
} else {
    echo "❌ {$config_path} bulunamadı<br>";
    echo "Mevcut klasörler:<br>";
    $dirs = glob('../*', GLOB_ONLYDIR);
    foreach($dirs as $dir) {
        echo "📁 " . basename($dir) . "<br>";
    }
}

echo "<br>";

// Database bağlantısını test et
echo "<h3>🔗 Database Bağlantı Test:</h3>";
try {
    if(file_exists($config_path)) {
        require_once $config_path;
        echo "✅ Config dosyası yüklendi<br>";
        
        if(isset($pdo)) {
            echo "✅ PDO bağlantısı mevcut<br>";
            
            // Basit bir sorgu çalıştır
            $result = $pdo->query("SELECT 1 as test")->fetch();
            if($result['test'] == 1) {
                echo "✅ Database bağlantısı çalışıyor!<br>";
                
                // Tabloları listele
                echo "<h3>📋 Mevcut Tablolar:</h3>";
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                foreach($tables as $table) {
                    echo "📦 " . $table . "<br>";
                }
            } else {
                echo "❌ Database sorgusu çalışmadı<br>";
            }
        } else {
            echo "❌ PDO değişkeni bulunamadı<br>";
        }
    } else {
        echo "❌ Config dosyası yüklenemedi<br>";
    }
} catch(Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>🎯 Sonraki Adım:</h3>";
echo "Eğer bu sayfa çalıştıysa, database.php dosyasında problem var.<br>";
echo "Eğer bu sayfa da 500 hatası verirse, PHP syntax hatası var.";
?>