<?php
echo "<h2>🔍 DatabaseConfig Dosyasını Bul</h2>";

// Olası DatabaseConfig dosya yolları
$possible_paths = [
    '../core/classes/DatabaseConfig.php',
    '../core/classes/Config.php', 
    '../config/DatabaseConfig.php',
    '../config/Config.php',
    '../core/config/DatabaseConfig.php',
    '../core/config/Config.php'
];

echo "<h3>📂 DatabaseConfig Arama:</h3>";
$found_config = false;

foreach($possible_paths as $path) {
    if(file_exists($path)) {
        echo "✅ Bulundu: {$path}<br>";
        echo "Dosya boyutu: " . filesize($path) . " byte<br>";
        
        echo "<h4>📖 İçerik (İlk 300 karakter):</h4>";
        echo "<pre style='background:#f5f5f5; padding:10px;'>";
        echo htmlspecialchars(substr(file_get_contents($path), 0, 300));
        echo "</pre>";
        
        $found_config = true;
        break;
    } else {
        echo "❌ Yok: {$path}<br>";
    }
}

if(!$found_config) {
    echo "<hr>";
    echo "<h3>❌ DatabaseConfig dosyası bulunamadı!</h3>";
    echo "<p>DatabaseConfig.php dosyasını oluşturmamız gerekiyor.</p>";
    
    echo "<h3>🔍 Core klasöründeki dosyalar:</h3>";
    $core_path = '../core/classes/';
    if(is_dir($core_path)) {
        $files = scandir($core_path);
        foreach($files as $file) {
            if($file != '.' && $file != '..') {
                echo "📄 " . $file;
                if(is_file($core_path . $file)) {
                    echo " (" . filesize($core_path . $file) . " byte)";
                }
                echo "<br>";
            }
        }
    }
    
    echo "<hr>";
    echo "<h3>🛠️ Çözüm Önerileri:</h3>";
    echo "<p>1. DatabaseConfig.php dosyasını oluşturalım</p>";
    echo "<p>2. Veya Database.php dosyasını DatabaseConfig olmadan çalışacak şekilde düzenleyelim</p>";
}

echo "<hr>";
echo "<a href='users.php'>⬅️ Users.php'ye Dön</a>";
?>