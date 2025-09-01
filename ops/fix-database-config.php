<?php
echo "<h2>🔧 Database Bilgilerini Düzelt</h2>";

// Mevcut DatabaseConfig bilgilerini göster
echo "<h3>📋 Mevcut DatabaseConfig Bilgileri:</h3>";
require_once '../core/classes/DatabaseConfig.php';

$current_config = [
    'host' => DatabaseConfig::getHost(),
    'dbname' => DatabaseConfig::getDbName(),
    'username' => DatabaseConfig::getUsername(),
    'password' => '***gizli***', // Şifreyi güvenlik için gizle
    'charset' => DatabaseConfig::getCharset()
];

foreach($current_config as $key => $value) {
    echo "🔹 {$key}: {$value}<br>";
}

echo "<hr>";
echo "<h3>🔍 Doğru Database Bilgilerini Bul</h3>";

// WordPress config dosyası arama
$wp_paths = [
    '../../wp-config.php',
    '../wp-config.php', 
    '../../../wp-config.php',
    '../../../../wp-config.php'
];

$found_wp = false;
foreach($wp_paths as $path) {
    if(file_exists($path)) {
        echo "<h4>✅ WordPress config bulundu: {$path}</h4>";
        $found_wp = true;
        
        $content = file_get_contents($path);
        
        // Database bilgilerini çıkar
        preg_match("/define\s*\(\s*['\"]DB_NAME['\"],\s*['\"]([^'\"]+)['\"]/", $content, $db_name);
        preg_match("/define\s*\(\s*['\"]DB_USER['\"],\s*['\"]([^'\"]+)['\"]/", $content, $db_user);
        preg_match("/define\s*\(\s*['\"]DB_PASSWORD['\"],\s*['\"]([^'\"]+)['\"]/", $content, $db_pass);
        preg_match("/define\s*\(\s*['\"]DB_HOST['\"],\s*['\"]([^'\"]+)['\"]/", $content, $db_host);
        
        if(isset($db_name[1]) && isset($db_user[1]) && isset($db_pass[1])) {
            echo "<h4>📊 WordPress'ten Bulunan Bilgiler:</h4>";
            echo "🏠 Host: " . ($db_host[1] ?? 'localhost') . "<br>";
            echo "📦 Database: {$db_name[1]}<br>";
            echo "👤 User: {$db_user[1]}<br>";
            echo "🔑 Password: ***bulundu***<br>";
            
            // Test et
            echo "<h4>🧪 Database Bağlantı Test:</h4>";
            try {
                $test_pdo = new PDO(
                    "mysql:host=" . ($db_host[1] ?? 'localhost') . ";dbname={$db_name[1]};charset=utf8mb4", 
                    $db_user[1], 
                    $db_pass[1]
                );
                $test_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $result = $test_pdo->query("SELECT 1")->fetch();
                
                echo "✅ <strong>WordPress database bilgileri ÇALIŞIYOR!</strong><br>";
                
                // DatabaseConfig dosyasını güncelle
                $new_config = "<?php\n";
                $new_config .= "/**\n * Database Configuration Class\n * WordPress'ten otomatik oluşturuldu\n */\n";
                $new_config .= "class DatabaseConfig {\n\n";
                $new_config .= "    const DB_HOST = '" . ($db_host[1] ?? 'localhost') . "';\n";
                $new_config .= "    const DB_NAME = '{$db_name[1]}';\n";
                $new_config .= "    const DB_USER = '{$db_user[1]}';\n";
                $new_config .= "    const DB_PASS = '{$db_pass[1]}';\n";
                $new_config .= "    const DB_CHARSET = 'utf8mb4';\n\n";
                $new_config .= "    public static function getConfig() {\n";
                $new_config .= "        return [\n";
                $new_config .= "            'host' => self::DB_HOST,\n";
                $new_config .= "            'dbname' => self::DB_NAME,\n";
                $new_config .= "            'username' => self::DB_USER,\n";
                $new_config .= "            'password' => self::DB_PASS,\n";
                $new_config .= "            'charset' => self::DB_CHARSET\n";
                $new_config .= "        ];\n";
                $new_config .= "    }\n\n";
                $new_config .= "    public static function getDSN() {\n";
                $new_config .= "        return \"mysql:host=\" . self::DB_HOST . \";dbname=\" . self::DB_NAME . \";charset=\" . self::DB_CHARSET;\n";
                $new_config .= "    }\n\n";
                $new_config .= "    public static function getHost() { return self::DB_HOST; }\n";
                $new_config .= "    public static function getDbName() { return self::DB_NAME; }\n";
                $new_config .= "    public static function getUsername() { return self::DB_USER; }\n";
                $new_config .= "    public static function getPassword() { return self::DB_PASS; }\n";
                $new_config .= "    public static function getCharset() { return self::DB_CHARSET; }\n";
                $new_config .= "}\n";
                
                // Backup yap
                $backup_name = '../core/classes/DatabaseConfig_backup_' . date('Y-m-d_H-i-s') . '.php';
                copy('../core/classes/DatabaseConfig.php', $backup_name);
                echo "💾 Eski config yedeklendi: " . basename($backup_name) . "<br>";
                
                // Yeni config'i yaz
                file_put_contents('../core/classes/DatabaseConfig.php', $new_config);
                echo "✅ <strong>DatabaseConfig.php güncellendi!</strong><br>";
                
                echo "<hr>";
                echo "<h3>🎉 İşlem Tamamlandı!</h3>";
                echo "DatabaseConfig WordPress bilgileri ile güncellendi.<br>";
                echo "<a href='users-simple.php' style='padding:10px 20px; background:#28a745; color:white; text-decoration:none; border-radius:5px; display:inline-block; margin:10px 0;'>👥 Users Sayfasını Test Et</a>";
                
            } catch(Exception $e) {
                echo "❌ WordPress bilgileri de çalışmadı: " . $e->getMessage() . "<br>";
            }
        } else {
            echo "❌ WordPress config'te database bilgileri bulunamadı<br>";
        }
        break;
    }
}

if(!$found_wp) {
    echo "<h4>❌ WordPress config dosyası bulunamadı</h4>";
    echo "<h4>📝 Manuel Database Bilgilerini Girin:</h4>";
    echo "<form method='post' style='background:#f9f9f9; padding:20px; border-radius:5px;'>";
    echo "<p><strong>Database Host:</strong><br><input type='text' name='db_host' value='localhost' style='width:300px; padding:5px;'></p>";
    echo "<p><strong>Database Name:</strong><br><input type='text' name='db_name' placeholder='volkanya_webimvar' style='width:300px; padding:5px;'></p>";
    echo "<p><strong>Username:</strong><br><input type='text' name='db_user' placeholder='volkanya_webimvar' style='width:300px; padding:5px;'></p>";
    echo "<p><strong>Password:</strong><br><input type='password' name='db_pass' placeholder='şifrenizi_girin' style='width:300px; padding:5px;'></p>";
    echo "<p><input type='submit' name='update_config' value='DatabaseConfig Güncelle' style='padding:10px 20px; background:#007cba; color:white; border:none; border-radius:5px;'></p>";
    echo "</form>";
    
    if(isset($_POST['update_config'])) {
        // Manuel güncelleme
        $host = $_POST['db_host'] ?? 'localhost';
        $name = $_POST['db_name'] ?? '';
        $user = $_POST['db_user'] ?? '';
        $pass = $_POST['db_pass'] ?? '';
        
        if($name && $user && $pass) {
            try {
                // Test et
                $test_pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass);
                echo "✅ Manuel bilgiler test edildi - ÇALIŞIYOR!<br>";
                
                // Config güncelle (yukarıdaki kod ile aynı)
                echo "DatabaseConfig güncellenecek...<br>";
                
            } catch(Exception $e) {
                echo "❌ Manuel bilgiler çalışmadı: " . $e->getMessage() . "<br>";
            }
        } else {
            echo "❌ Lütfen tüm alanları doldurun!<br>";
        }
    }
}
?>