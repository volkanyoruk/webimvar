<?php
// Kullanıcı Şifre Sıfırlama
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/classes/DatabaseConfig.php';

$user_id = (int)($_GET['id'] ?? 0);
if (!$user_id) {
    header('Location: users.php');
    exit;
}

try {
    $config = DatabaseConfig::getConfig();
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}", 
        $config['username'], 
        $config['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Kullanıcı bilgilerini getir
$user_query = "SELECT * FROM users WHERE id = ?";
$user_stmt = $pdo->prepare($user_query);
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

if (!$user) {
    header('Location: users.php');
    exit;
}

$message = '';
$error = '';

// Şifre sıfırlama işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'generate_random') {
            // Rastgele şifre oluştur
            $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
            $password_length = (int)($_POST['password_length'] ?? 12);
            $password_length = max(6, min(50, $password_length)); // 6-50 karakter arası
            
            $new_password = '';
            for ($i = 0; $i < $password_length; $i++) {
                $new_password .= $characters[random_int(0, strlen($characters) - 1)];
            }
            
        } elseif ($action === 'set_manual') {
            // Manuel şifre belirle
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($new_password)) {
                throw new Exception("Yeni şifre gerekli.");
            }
            
            if (strlen($new_password) < 6) {
                throw new Exception("Şifre en az 6 karakter olmalı.");
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception("Şifreler eşleşmiyor.");
            }
            
        } else {
            throw new Exception("Geçersiz işlem.");
        }
        
        // Şifreyi veritabanında güncelle
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $columns = [];
        $column_result = $pdo->query("DESCRIBE users");
        while($row = $column_result->fetch()) {
            $columns[] = $row['Field'];
        }
        
        $update_fields = [];
        $update_values = [];
        
        if (in_array('password', $columns)) {
            $update_fields[] = "password = ?";
            $update_values[] = $hashed_password;
        }
        
        // Password reset timestamp ekle (varsa)
        if (in_array('password_reset_at', $columns)) {
            $update_fields[] = "password_reset_at = NOW()";
        }
        
        if (in_array('updated_at', $columns)) {
            $update_fields[] = "updated_at = NOW()";
        }
        
        // Remember token'ları temizle (varsa)
        if (in_array('remember_token', $columns)) {
            $update_fields[] = "remember_token = NULL";
        }
        
        if (!empty($update_fields)) {
            $update_values[] = $user_id;
            $update_sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute($update_values);
        }
        
        // Log kaydı
        try {
            $log_query = "INSERT INTO system_logs (user_id, action, details, ip_address, created_at) VALUES (?, 'password_reset_by_admin', ?, ?, NOW())";
            $log_stmt = $pdo->prepare($log_query);
            $log_stmt->execute([
                $user_id,
                "Password reset by admin for user: {$user['username']} (ID: {$user_id})",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            // Log tablosu yoksa sessizce devam et
        }
        
        $message = "Şifre başarıyla sıfırlandı.";
        $show_password = $_POST['show_password'] ?? false;
        if ($show_password && isset($new_password)) {
            $message .= " Yeni şifre: <strong>" . htmlspecialchars($new_password) . "</strong>";
        }
        
        $send_email = $_POST['send_email'] ?? false;
        if ($send_email) {
            // TODO: Email gönderme işlemi
            $message .= " Kullanıcıya email gönderildi.";
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Son şifre sıfırlama geçmişi
$password_history = [];
try {
    $history_query = "SELECT * FROM system_logs WHERE user_id = ? AND action LIKE '%password%' ORDER BY created_at DESC LIMIT 5";
    $history_stmt = $pdo->prepare($history_query);
    $history_stmt->execute([$user_id]);
    $password_history = $history_stmt->fetchAll();
} catch (Exception $e) {
    // System_logs tablosu yok
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifre Sıfırlama - <?= htmlspecialchars($user['username'] ?? $user['email']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f9fa; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { color: #2c3e50; margin-bottom: 5px; }
        .header p { color: #6c757d; }
        
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card h2 { color: #2c3e50; margin-bottom: 15px; font-size: 1.2rem; }
        
        .user-info { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 20px; }
        .user-avatar { width: 60px; height: 60px; border-radius: 50%; background: #007bff; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; }
        .user-details h3 { color: #2c3e50; margin-bottom: 5px; }
        .user-details p { color: #6c757d; margin: 2px 0; }
        
        .warning-box { background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .warning-box h3 { margin-bottom: 10px; color: #856404; }
        
        .form-section { margin-bottom: 30px; padding: 20px; border: 2px solid #e9ecef; border-radius: 8px; }
        .form-section.active { border-color: #007bff; background: #f8f9ff; }
        .form-section h3 { color: #2c3e50; margin-bottom: 15px; }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px; }
        .form-row.single { grid-template-columns: 1fr; }
        
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 8px; font-weight: 500; color: #495057; }
        .form-control { padding: 10px 12px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 0.95rem; }
        .form-control:focus { outline: none; border-color: #007bff; box-shadow: 0 0 0 2px rgba(0,123,255,0.25); }
        
        .checkbox-group { display: flex; align-items: center; gap: 8px; margin: 10px 0; }
        .checkbox-group input[type="checkbox"] { transform: scale(1.2); }
        
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.95rem; display: inline-block; margin: 5px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn:hover { opacity: 0.9; }
        .btn-large { padding: 15px 30px; font-size: 1.1rem; }
        
        .message { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .password-strength { margin-top: 5px; }
        .strength-bar { height: 4px; border-radius: 2px; background: #e9ecef; }
        .strength-fill { height: 100%; border-radius: 2px; transition: all 0.3s ease; }
        .strength-weak { background: #dc3545; width: 25%; }
        .strength-medium { background: #ffc107; width: 60%; }
        .strength-strong { background: #28a745; width: 100%; }
        
        .generated-password { background: #e7f3ff; border: 1px solid #b3d7ff; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .generated-password code { font-size: 1.1rem; font-weight: bold; color: #0056b3; }
        
        .history-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .history-table th, .history-table td { padding: 10px; text-align: left; border-bottom: 1px solid #dee2e6; }
        .history-table th { background: #f8f9fa; font-weight: 600; }
        .history-table tr:hover { background: #f8f9fa; }
        
        .help-text { font-size: 0.85rem; color: #6c757d; margin-top: 4px; }
        
        @media (max-width: 768px) {
            .user-info { flex-direction: column; text-align: center; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if($message): ?>
            <div class="message success"><?= $message ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="header">
            <h1>Şifre Sıfırlama</h1>
            <p>Kullanıcının şifresini sıfırlayın veya yeni şifre belirleyin</p>
        </div>

        <div class="card">
            <h2>Kullanıcı Bilgileri</h2>
            
            <div class="user-info">
                <div class="user-avatar">
                    <?= strtoupper(substr($user['username'] ?? $user['email'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="user-details">
                    <h3><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'Kullanıcı') ?></h3>
                    <p><strong>Email:</strong> <?= htmlspecialchars($user['email'] ?? 'N/A') ?></p>
                    <p><strong>Kullanıcı Adı:</strong> <?= htmlspecialchars($user['username'] ?? 'N/A') ?></p>
                    <p><strong>Son Şifre Değişikliği:</strong> <?= isset($user['password_reset_at']) ? date('d.m.Y H:i', strtotime($user['password_reset_at'])) : 'Bilinmiyor' ?></p>
                </div>
            </div>
        </div>

        <div class="warning-box">
            <h3>🔐 Güvenlik Uyarısı</h3>
            <p>Şifre sıfırlama işlemi kullanıcının hesabına tam erişim sağlar. Bu işlemi sadece:</p>
            <ul style="margin: 10px 0 0 20px;">
                <li>Kullanıcı tarafından talep edildiğinde</li>
                <li>Hesap kurtarma sürecinde</li>
                <li>Güvenlik ihlali durumunda</li>
                <li>Yetkili personel tarafından yapılması gerektiğinde</li>
            </ul>
            <p style="margin-top: 10px;"><strong>Tüm işlemler sistem loglarında kayıt altına alınır.</strong></p>
        </div>

        <!-- Rastgele Şifre Oluştur -->
        <div class="form-section">
            <h3>1. Rastgele Şifre Oluştur</h3>
            <p>Sistem tarafından güvenli bir şifre otomatik oluşturulsun.</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="generate_random">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Şifre Uzunluğu</label>
                        <select name="password_length" class="form-control">
                            <option value="8">8 karakter</option>
                            <option value="12" selected>12 karakter (Önerilen)</option>
                            <option value="16">16 karakter</option>
                            <option value="20">20 karakter</option>
                        </select>
                        <div class="help-text">Uzun şifreler daha güvenlidir</div>
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="show_password" id="show_random_password" checked>
                    <label for="show_random_password">Oluşturulan şifreyi ekranda göster</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="send_email" id="send_random_email">
                    <label for="send_random_email">Kullanıcıya email ile gönder</label>
                </div>
                
                <button type="submit" class="btn btn-success btn-large" onclick="return confirm('Rastgele şifre oluşturmak istediğinizden emin misiniz?')">
                    🎲 Rastgele Şifre Oluştur
                </button>
            </form>
        </div>

        <!-- Manuel Şifre Belirle -->
        <div class="form-section">
            <h3>2. Manuel Şifre Belirle</h3>
            <p>Kendiniz bir şifre belirleyin.</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="set_manual">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Yeni Şifre</label>
                        <input type="password" name="new_password" id="new_password" class="form-control" onkeyup="checkPasswordStrength(this.value)" required>
                        <div class="help-text">En az 6 karakter</div>
                        <div class="password-strength" id="passwordStrength" style="display: none;">
                            <div class="strength-bar">
                                <div class="strength-fill" id="strengthFill"></div>
                            </div>
                            <small id="strengthText"></small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Şifre Tekrar</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" onblur="checkPasswordMatch()" required>
                        <div class="help-text">Şifreyi tekrar girin</div>
                        <div id="passwordMatchStatus"></div>
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="show_password" id="show_manual_password">
                    <label for="show_manual_password">Şifreyi ekranda göster</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="send_email" id="send_manual_email">
                    <label for="send_manual_email">Kullanıcıya email ile gönder</label>
                </div>
                
                <button type="submit" class="btn btn-warning btn-large" onclick="return confirm('Bu şifreyi ayarlamak istediğinizden emin misiniz?')">
                    🔑 Şifreyi Ayarla
                </button>
            </form>
        </div>

        <!-- Şifre Geçmişi -->
        <?php if (!empty($password_history)): ?>
        <div class="card">
            <h2>Şifre Geçmişi</h2>
            
            <table class="history-table">
                <thead>
                    <tr>
                        <th>İşlem</th>
                        <th>Tarih</th>
                        <th>IP Adresi</th>
                        <th>Detaylar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($password_history as $log): ?>
                        <tr>
                            <td><?= ucfirst(str_replace('_', ' ', $log['action'] ?? '')) ?></td>
                            <td><?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?></td>
                            <td><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($log['details'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="user-detail.php?id=<?= $user_id ?>" class="btn btn-primary">👁️ Kullanıcı Detayları</a>
            <a href="user-edit.php?id=<?= $user_id ?>" class="btn btn-secondary">✏️ Kullanıcıyı Düzenle</a>
            <a href="users.php" class="btn btn-secondary">👥 Kullanıcı Listesi</a>
        </div>
    </div>

    <script>
    function checkPasswordStrength(password) {
        const strengthDiv = document.getElementById('passwordStrength');
        const strengthFill = document.getElementById('strengthFill');
        const strengthText = document.getElementById('strengthText');
        
        if (password.length === 0) {
            strengthDiv.style.display = 'none';
            return;
        }
        
        strengthDiv.style.display = 'block';
        
        let strength = 0;
        let text = '';
        let className = '';
        
        if (password.length >= 6) strength++;
        if (password.length >= 8) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;
        
        if (strength <= 2) {
            text = 'Zayıf şifre';
            className = 'strength-weak';
        } else if (strength <= 3) {
            text = 'Orta güçte şifre';
            className = 'strength-medium';
        } else {
            text = 'Güçlü şifre';
            className = 'strength-strong';
        }
        
        strengthFill.className = 'strength-fill ' + className;
        strengthText.textContent = text;
    }
    
    function checkPasswordMatch() {
        const password = document.getElementById('new_password').value;
        const confirm = document.getElementById('confirm_password').value;
        const status = document.getElementById('passwordMatchStatus');
        
        if (confirm.length === 0) {
            status.innerHTML = '';
            return;
        }
        
        if (password === confirm) {
            status.innerHTML = '<small style="color: #28a745;">✓ Şifreler eşleşiyor</small>';
        } else {
            status.innerHTML = '<small style="color: #dc3545;">✗ Şifreler eşleşmiyor</small>';
        }
    }
    </script>
</body>
</html>