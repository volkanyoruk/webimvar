<?php
echo "<h2>🔍 Database.php İçeriğini İncele</h2>";

$database_path = '../core/classes/Database.php';

if(file_exists($database_path)) {
    echo "<h3>📖 Database.php İçeriği:</h3>";
    
    $content = file_get_contents($database_path);
    $lines = explode("\n", $content);
    
    echo "<pre style='background:#f5f5f5; padding:10px; overflow-x:auto; max-height:400px;'>";
    foreach($lines as $i => $line) {
        $line_num = $i + 1;
        if($line_num >= 30 && $line_num <= 40) {
            echo "<strong style='color:red;'>$line_num: " . htmlspecialchars($line) . "</strong>\n";
        } else {
            echo "$line_num: " . htmlspecialchars($line) . "\n";
        }
    }
    echo "</pre>";
    
    echo "<h3>🔍 DatabaseConfig require kontrol:</h3>";
    if(strpos($content, 'require') !== false || strpos($content, 'include') !== false) {
        echo "✅ Dosyada require/include var<br>";
        preg_match_all('/(?:require|include)(?:_once)?\s*[(\'"](.*?)[\'")]/i', $content, $matches);
        if(!empty($matches[1])) {
            echo "📂 İçe aktarılan dosyalar:<br>";
            foreach($matches[1] as $file) {
                echo "- " . $file . "<br>";
            }
        }
    } else {
        echo "❌ Dosyada require/include yok - DatabaseConfig dahil edilmiyor!<br>";
    }
    
    echo "<h3>🔍 DatabaseConfig kullanımı:</h3>";
    if(strpos($content, 'DatabaseConfig') !== false) {
        echo "✅ DatabaseConfig class'ı kullanılıyor<br>";
        $db_config_lines = [];
        foreach($lines as $i => $line) {
            if(strpos($line, 'DatabaseConfig') !== false) {
                $db_config_lines[] = ($i + 1) . ": " . trim($line);
            }
        }
        echo "📍 DatabaseConfig kullanılan satırlar:<br>";
        foreach($db_config_lines as $line) {
            echo "- " . htmlspecialchars($line) . "<br>";
        }
    } else {
        echo "❌ DatabaseConfig class'ı kullanılmıyor<br>";
    }
} else {
    echo "❌ Database.php dosyası bulunamadı!<br>";
}

echo "<hr>";
echo "<h3>🛠️ Çözüm:</h3>";
echo "Database.php dosyasına DatabaseConfig.php'yi require etmemiz gerekiyor.";
?>