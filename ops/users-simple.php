<?php
// Basit kullanıcı yönetimi - Database class'ı kullanmadan
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>👥 Kullanıcı Yönetimi (Basit PDO)</h2>";

// DatabaseConfig bilgilerini al
require_once '../core/classes/DatabaseConfig.php';

try {
    // Direct PDO connection
    $config = DatabaseConfig::getConfig();
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}", 
        $config['username'], 
        $config['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h3>✅ Database Bağlantısı Başarılı!</h3>";
    
    // Mevcut tabloları listele
    echo "<h3>📊 Mevcut Tablolar:</h3>";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach($tables as $table) {
        echo "📦 " . $table . "<br>";
    }
    
    // Users tablosu kontrol et
    if(in_array('users', $tables)) {
        echo "<h3>✅ Users Tablosu Mevcut!</h3>";
        
        // Kullanıcı sayısını al
        $user_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        echo "👥 Toplam kullanıcı: <strong>{$user_count}</strong><br><br>";
        
        if($user_count > 0) {
            // Kullanıcıları listele
            $users = $pdo->query("SELECT * FROM users ORDER BY id DESC LIMIT 10")->fetchAll();
            
            echo "<h3>👥 Kullanıcı Listesi (Son 10):</h3>";
            echo "<div style='overflow-x:auto;'>";
            echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse; width:100%;'>";
            echo "<tr style='background:#f0f0f0;'>";
            echo "<th>ID</th><th>Kullanıcı Adı</th><th>Email</th><th>İsim</th><th>Durum</th><th>Kayıt Tarihi</th><th>İşlemler</th>";
            echo "</tr>";
            
            foreach($users as $user) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($user['id'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($user['username'] ?? $user['kullanici_adi'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($user['email'] ?? $user['mail'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($user['full_name'] ?? $user['ad_soyad'] ?? $user['isim'] ?? '') . "</td>";
                
                $status = $user['status'] ?? $user['durum'] ?? 'unknown';
                $status_color = $status == 'active' ? 'green' : ($status == 'inactive' ? 'orange' : 'red');
                echo "<td><span style='color:{$status_color}; font-weight:bold;'>" . $status . "</span></td>";
                
                echo "<td>" . htmlspecialchars($user['created_at'] ?? $user['kayit_tarihi'] ?? '') . "</td>";
                echo "<td>";
                echo "<button style='margin:2px; padding:3px 6px; font-size:11px;'>Düzenle</button> ";
                echo "<button style='margin:2px; padding:3px 6px; font-size:11px;'>Sil</button>";
                echo "</td>";
                echo "</tr>";
            }
            echo "</table></div>";
        } else {
            echo "<p>ℹ️ Users tablosunda henüz kayıt yok.</p>";
            echo "<button style='padding:10px 20px; background:#007cba; color:white; border:none; border-radius:5px;'>Test Kullanıcıları Ekle</button>";
        }
    } else {
        echo "<h3>❌ Users Tablosu Yok</h3>";
        echo "<p>Users tablosunu oluşturmamız gerekiyor.</p>";
        echo "<button style='padding:10px 20px; background:#28a745; color:white; border:none; border-radius:5px;'>Users Tablosunu Oluştur</button>";
    }
    
} catch(Exception $e) {
    echo "<h3>❌ Database Hatası:</h3>";
    echo "<p style='color:red; background:#ffe6e6; padding:10px; border:1px solid red;'>";
    echo htmlspecialchars($e->getMessage());
    echo "</p>";
    
    echo "<h3>🔍 Kontrol Edilecek Noktalar:</h3>";
    echo "<ul>";
    echo "<li>DatabaseConfig.php dosyasındaki bilgiler doğru mu?</li>";
    echo "<li>MySQL servisi çalışıyor mu?</li>";
    echo "<li>Database adı, kullanıcı adı ve şifre doğru mu?</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<div style='text-align:center; margin:20px 0;'>";
echo "<a href='packages.php' style='margin:10px; padding:10px 20px; background:#007cba; color:white; text-decoration:none; border-radius:5px;'>📦 Paket Yönetimi</a>";
echo "<a href='check-database-php.php' style='margin:10px; padding:10px 20px; background:#6c757d; color:white; text-decoration:none; border-radius:5px;'>🔍 Database.php İncele</a>";
echo "</div>";
?>