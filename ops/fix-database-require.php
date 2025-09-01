<?php
echo "<h2>🔧 Database.php Düzeltme</h2>";

$database_file = '../core/classes/Database.php';

if(file_exists($database_file)) {
    echo "<h3>📖 Mevcut Database.php içeriği kontrol ediliyor...</h3>";
    
    $content = file_get_contents($database_file);
    
    // DatabaseConfig require var mı kontrol et
    if(strpos($content, 'require') !== false && strpos($content, 'DatabaseConfig') !== false) {
        echo "✅ DatabaseConfig zaten require edilmiş<br>";
    } else {
        echo "❌ DatabaseConfig require edilmemiş - ekleniyor...<br>";
        
        // Backup yap
        $backup_name = '../core/classes/Database_backup_' . date('Y-m-d_H-i-s') . '.php';
        copy($database_file, $backup_name);
        echo "💾 Backup oluşturuldu: " . basename($backup_name) . "<br>";
        
        // DatabaseConfig require'ını ekle
        // <?php etiketinden sonra hemen ekle
        $new_content = $content;
        
        // İlk <?php satırını bul
        if(preg_match('/^(<\?php.*?\n)/m', $content, $matches)) {
            $php_tag = $matches[1];
            $rest_content = str_replace($php_tag, '', $content);
            
            // Yeni içerik oluştur
            $new_content = $php_tag;
            $new_content .= "// DatabaseConfig require - otomatik eklendi\n";
            $new_content .= "require_once __DIR__ . '/DatabaseConfig.php';\n\n";
            $new_content .= $rest_content;
            
            // Dosyayı güncelle
            file_put_contents($database_file, $new_content);
            echo "✅ <strong>Database.php güncellendi!</strong><br>";
            echo "📝 DatabaseConfig require eklendi<br>";
            
        } else {
            echo "❌ PHP etiketi bulunamadı - manuel düzenleme gerekiyor<br>";
        }
    }
    
    echo "<hr>";
    echo "<h3>🧪 Database Bağlantısı Test</h3>";
    
    try {
        // Güncellenmiş Database class'ını test et
        require_once $database_file;
        $db = Database::getInstance();
        echo "✅ <strong>Database bağlantısı BAŞARILI!</strong><br>";
        
        // Test sorgusu
        if(method_exists($db, 'query')) {
            $result = $db->query("SELECT 1 as test");
            if($result) {
                echo "✅ Database sorgusu çalışıyor<br>";
            }
        }
        
        echo "<hr>";
        echo "<h3>🎉 Sorun Çözüldü!</h3>";
        echo "Artık users.php çalışacak.<br>";
        echo "<a href='users.php' style='padding:10px 20px; background:#28a745; color:white; text-decoration:none; border-radius:5px; display:inline-block; margin:10px 0;'>👥 Users Sayfasını Test Et</a>";
        
    } catch(Exception $e) {
        echo "❌ Hala hata var: " . $e->getMessage() . "<br>";
        echo "<h4>🔍 Alternatif çözüm gerekiyor</h4>";
        
        // Alternatif basit users.php sun
        echo "<p>Database class'ı sorunlu. Basit PDO ile users.php oluşturalım:</p>";
        echo "<a href='users-simple.php' style='padding:10px 20px; background:#007cba; color:white; text-decoration:none; border-radius:5px; display:inline-block; margin:10px 0;'>👥 Basit Users Sayfası</a>";
    }
} else {
    echo "❌ Database.php dosyası bulunamadı!<br>";
}
?>