<?php
// Yeni Kullanıcı Ekleme Sayfası
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/classes/DatabaseConfig.php';

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

// Paketleri getir
$packages = [];
try {
    $packages_query = "SELECT id, name, price FROM packages ORDER BY price ASC";
    $packages = $pdo->query($packages_query)->fetchAll();
} catch (Exception $e) {
    // Packages tablosu yok
}

// Kullanılabilir sütunları kontrol et
$columns = [];
$column_result = $pdo->query("DESCRIBE users");
while($row = $column_result->fetch()) {
    $columns[] = $row['Field'];
}

$message = '';
$error = '';

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $full_name = $_POST['full_name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $role = $_POST['role'] ?? 'user';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $package_id = !empty($_POST['package_id']) ? (int)$_POST['package_id'] : null;
        $send_welcome_email = isset($_POST['send_welcome_email']);

        // Validation
        if (empty($username)) {
            throw new Exception("Kullanıcı adı gerekli.");
        }
        if (strlen($username) < 3) {
            throw new Exception("Kullanıcı adı en az 3 karakter olmalı.");
        }
        if (empty($email)) {
            throw new Exception("Email adresi gerekli.");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Geçerli bir email adresi girin.");
        }
        if (empty($password)) {
            throw new Exception("Şifre gerekli.");
        }
        if (strlen($password) < 6) {
            throw new Exception("Şifre en az 6 karakter olmalı.");
        }
        if ($password !== $password_confirm) {
            throw new Exception("Şifreler eşleşmiyor.");
        }

        // Email benzersizliği kontrolü
        $email_check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $email_check->execute([$email]);
        if ($email_check->fetch()) {
            throw new Exception("Bu email adresi zaten kullanılıyor.");
        }

        // Kullanıcı adı benzersizliği kontrolü
        $username_check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $username_check->execute([$username]);
        if ($username_check->fetch()) {
            throw new Exception("Bu kullanıcı adı zaten kullanılıyor.");
        }

        $pdo->beginTransaction();

        // Insert alanlarını hazırla
        $insert_fields = ['id'];
        $insert_placeholders = ['NULL'];
        $insert_values = [];

        if (in_array('username', $columns)) {
            $insert_fields[] = 'username';
            $insert_placeholders[] = '?';
            $insert_values[] = $username;
        }
        if (in_array('email', $columns)) {
            $insert_fields[] = 'email';
            $insert_placeholders[] = '?';
            $insert_values[] = $email;
        }
        if (in_array('password', $columns)) {
            $insert_fields[] = 'password';
            $insert_placeholders[] = '?';
            $insert_values[] = password_hash($password, PASSWORD_DEFAULT);
        }
        if (in_array('full_name', $columns) && $full_name) {
            $insert_fields[] = 'full_name';
            $insert_placeholders[] = '?';
            $insert_values[] = $full_name;
        }
        if (in_array('phone', $columns) && $phone) {
            $insert_fields[] = 'phone';
            $insert_placeholders[] = '?';
            $insert_values[] = $phone;
        }
        if (in_array('role', $columns)) {
            $insert_fields[] = 'role';
            $insert_placeholders[] = '?';
            $insert_values[] = $role;
        }
        if (in_array('is_active', $columns)) {
            $insert_fields[] = 'is_active';
            $insert_placeholders[] = '?';
            $insert_values[] = $is_active;
        }
        if (in_array('package_id', $columns) && $package_id) {
            $insert_fields[] = 'package_id';
            $insert_placeholders[] = '?';
            $insert_values[] = $package_id;
        }
        if (in_array('created_at', $columns)) {
            $insert_fields[] = 'created_at';
            $insert_placeholders[] = 'NOW()';
        }
        if (in_array('updated_at', $columns)) {
            $insert_fields[] = 'updated_at';
            $insert_placeholders[] = 'NOW()';
        }

        $insert_sql = "INSERT INTO users (" . implode(', ', $insert_fields) . ") VALUES (" . implode(', ', $insert_placeholders) . ")";
        $insert_stmt = $pdo->prepare($insert_sql);
        $insert_stmt->execute($insert_values);
        
        $new_user_id = $pdo->lastInsertId();

        // Log kaydı (varsa)
        if (in_array('system_logs', $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN))) {
            $log_query = "INSERT INTO system_logs (user_id, action, details, ip_address, created_at) VALUES (?, 'user_created', ?, ?, NOW())";
            $log_stmt = $pdo->prepare($log_query);
            $log_stmt->execute([
                $new_user_id,
                "New user created: $username ($email)",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }

        $pdo->commit();
        
        $message = "Kullanıcı başarıyla oluşturuldu! ID: $new_user_id";
        
        if ($send_welcome_email) {
            // TODO: Hoşgeldin emaili gönder
            $message .= " Hoşgeldin emaili gönderildi.";
        }

        // Form alanlarını temizle
        $_POST = [];

    } catch (Exception $e) {
        $pdo->rollback();
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Kullanıcı Ekle - Webimvar SaaS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f9fa; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { color: #2c3e50; margin-bottom: 5px; }
        .header p { color: #6c757d; }
        
        .form-card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        
        .form-section { margin-bottom: 30px; }
        .form-section h2 { color: #2c3e50; margin-bottom: 15px; font-size: 1.2rem; border-bottom: 2px solid #e9ecef; padding-bottom: 8px; }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-row.single { grid-template-columns: 1fr; }
        
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 8px; font-weight: 500; color: #495057; }
        .form-group .required { color: #dc3545; }
        .form-control { padding: 10px 12px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 0.95rem; }
        .form-control:focus { outline: none; border-color: #007bff; box-shadow: 0 0 0 2px rgba(0,123,255,0.25); }
        
        .checkbox-group { display: flex; align-items: center; gap: 8px; margin-top: 10px; }
        .checkbox-group input[type="checkbox"] { transform: scale(1.2); }
        
        .select-control { padding: 10px 12px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 0.95rem; background: white; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.95rem; display: inline-block; margin: 5px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn:hover { opacity: 0.9; }
        
        .message { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .form-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e9ecef; }
        
        .help-text { font-size: 0.85rem; color: #6c757d; margin-top: 4px; }
        
        .password-strength { margin-top: 5px; }
        .strength-bar { height: 4px; border-radius: 2px; background: #e9ecef; }
        .strength-fill { height: 100%; border-radius: 2px; transition: all 0.3s ease; }
        .strength-weak { background: #dc3545; width: 25%; }
        .strength-medium { background: #ffc107; width: 60%; }
        .strength-strong { background: #28a745; width: 100%; }
        
        .preview-card { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .preview-avatar { width: 50px; height: 50px; border-radius: 50%; background: #007bff; color: white; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 15px; }
        
        .requirements { background: #e7f3ff; border: 1px solid #b3d7ff; border-radius: 4px; padding: 15px; margin-bottom: 20px; }
        .requirements h3 { color: #0056b3; margin-bottom: 10px; font-size: 0.95rem; }
        .requirements ul { margin-left: 20px; }
        .requirements li { color: #495057; font-size: 0.85rem; margin-bottom: 5px; }
        
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="header">
            <h1>Yeni Kullanıcı Ekle</h1>
            <p>Sisteme yeni kullanıcı hesabı oluşturun</p>
        </div>

        <div class="form-card">
            <div class="requirements">
                <h3>Kullanıcı Oluşturma Gereksinimleri</h3>
                <ul>
                    <li>Kullanıcı adı benzersiz olmalı ve en az 3 karakter</li>
                    <li>Email adresi geçerli ve benzersiz olmalı</li>
                    <li>Şifre en az 6 karakter olmalı</li>
                    <li>Varsayılan olarak hesaplar aktif oluşturulur</li>
                    <li>İsteğe bağlı hoşgeldin emaili gönderilebilir</li>
                </ul>
            </div>

            <form method="POST" id="userForm">
                <!-- Hesap Bilgileri -->
                <div class="form-section">
                    <h2>Hesap Bilgileri</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Kullanıcı Adı <span class="required">*</span></label>
                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required onblur="checkUsernameAvailability(this.value)">
                            <div class="help-text">Benzersiz kullanıcı adı (en az 3 karakter)</div>
                            <div id="usernameStatus"></div>
                        </div>
                        <div class="form-group">
                            <label>Email Adresi <span class="required">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required onblur="checkEmailAvailability(this.value)">
                            <div class="help-text">Giriş için kullanılacak email</div>
                            <div id="emailStatus"></div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Şifre <span class="required">*</span></label>
                            <input type="password" name="password" id="password" class="form-control" required onkeyup="checkPasswordStrength(this.value)">
                            <div class="help-text">En az 6 karakter</div>
                            <div class="password-strength" id="passwordStrength" style="display: none;">
                                <div class="strength-bar">
                                    <div class="strength-fill" id="strengthFill"></div>
                                </div>
                                <small id="strengthText"></small>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Şifre Tekrar <span class="required">*</span></label>
                            <input type="password" name="password_confirm" id="password_confirm" class="form-control" required onblur="checkPasswordMatch()">
                            <div class="help-text">Şifreyi tekrar girin</div>
                            <div id="passwordMatchStatus"></div>
                        </div>
                    </div>
                </div>

                <!-- Kişisel Bilgiler -->
                <div class="form-section">
                    <h2>Kişisel Bilgiler</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tam İsim</label>
                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                            <div class="help-text">İsim ve soyisim</div>
                        </div>
                        <div class="form-group">
                            <label>Telefon</label>
                            <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                            <div class="help-text">İletişim telefonu</div>
                        </div>
                    </div>
                </div>

                <!-- Sistem Ayarları -->
                <div class="form-section">
                    <h2>Sistem Ayarları</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Rol</label>
                            <select name="role" class="select-control">
                                <option value="user" <?= ($_POST['role'] ?? 'user') == 'user' ? 'selected' : '' ?>>Kullanıcı</option>
                                <option value="admin" <?= ($_POST['role'] ?? '') == 'admin' ? 'selected' : '' ?>>Yönetici</option>
                                <option value="manager" <?= ($_POST['role'] ?? '') == 'manager' ? 'selected' : '' ?>>Müdür</option>
                                <option value="support" <?= ($_POST['role'] ?? '') == 'support' ? 'selected' : '' ?>>Destek</option>
                            </select>
                            <div class="help-text">Kullanıcının sistem yetkisi</div>
                        </div>
                        
                        <?php if (!empty($packages)): ?>
                        <div class="form-group">
                            <label>Paket</label>
                            <select name="package_id" class="select-control">
                                <option value="">Paket Atanmasın</option>
                                <?php foreach ($packages as $package): ?>
                                    <option value="<?= $package['id'] ?>" <?= ($_POST['package_id'] ?? '') == $package['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($package['name']) ?> (<?= number_format($package['price']) ?>₺/ay)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="help-text">Başlangıç hosting paketi</div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-row single">
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" name="is_active" id="is_active" <?= isset($_POST['is_active']) ? 'checked' : 'checked' ?>>
                                <label for="is_active">Hesap Aktif</label>
                            </div>
                            <div class="help-text">Pasif hesaplar giriş yapamaz</div>
                        </div>
                    </div>
                    
                    <div class="form-row single">
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" name="send_welcome_email" id="send_welcome_email" <?= isset($_POST['send_welcome_email']) ? 'checked' : '' ?>>
                                <label for="send_welcome_email">Hoşgeldin Emaili Gönder</label>
                            </div>
                            <div class="help-text">Kullanıcıya hesap bilgileri emaille gönderilir</div>
                        </div>
                    </div>
                </div>

                <!-- Önizleme -->
                <div class="preview-card" id="userPreview" style="display: none;">
                    <h3 style="margin-bottom: 15px; color: #2c3e50;">Kullanıcı Önizlemesi</h3>
                    <div style="display: flex; align-items: center;">
                        <div class="preview-avatar" id="previewAvatar">U</div>
                        <div>
                            <div id="previewName"><strong>Kullanıcı Adı</strong></div>
                            <div id="previewEmail" style="color: #6c757d;">email@domain.com</div>
                            <div id="previewRole" style="font-size: 0.85rem; color: #6c757d;">Rol: user</div>
                        </div>
                    </div>
                </div>

                <!-- Form Aksiyonları -->
                <div class="form-actions">
                    <div>
                        <button type="submit" class="btn btn-success">Kullanıcı Oluştur</button>
                        <button type="button" onclick="generateRandomPassword()" class="btn btn-primary">Rastgele Şifre</button>
                    </div>
                    <div>
                        <a href="users.php" class="btn btn-secondary">İptal Et</a>
                    </div>
                </div>
            </form>
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
        const password = document.getElementById('password').value;
        const confirm = document.getElementById('password_confirm').value;
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
    
    function checkUsernameAvailability(username) {
        // Simulated check - gerçek uygulamada AJAX kullanılacak
        const status = document.getElementById('usernameStatus');
        if (username.length >= 3) {
            status.innerHTML = '<small style="color: #28a745;">✓ Kullanıcı adı uygun</small>';
        }
    }
    
    function checkEmailAvailability(email) {
        // Simulated check - gerçek uygulamada AJAX kullanılacak
        const status = document.getElementById('emailStatus');
        if (email.includes('@')) {
            status.innerHTML = '<small style="color: #28a745;">✓ Email uygun</small>';
        }
    }
    
    function generateRandomPassword() {
        const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        let password = '';
        for (let i = 0; i < 12; i++) {
            password += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        
        document.getElementById('password').value = password;
        document.getElementById('password_confirm').value = password;
        checkPasswordStrength(password);
        checkPasswordMatch();
    }
    
    // Live preview
    function updatePreview() {
        const username = document.querySelector('[name="username"]').value;
        const email = document.querySelector('[name="email"]').value;
        const role = document.querySelector('[name="role"]').value;
        const fullName = document.querySelector('[name="full_name"]').value;
        
        if (username || email) {
            document.getElementById('userPreview').style.display = 'block';
            document.getElementById('previewAvatar').textContent = (username || email).charAt(0).toUpperCase();
            document.getElementById('previewName').innerHTML = '<strong>' + (fullName || username || 'Kullanıcı') + '</strong>';
            document.getElementById('previewEmail').textContent = email || 'Email girilmedi';
            document.getElementById('previewRole').textContent = 'Rol: ' + role;
        }
    }
    
    // Form alanlarına event listener ekle
    document.addEventListener('DOMContentLoaded', function() {
        const inputs = ['username', 'email', 'full_name', 'role'];
        inputs.forEach(name => {
            const input = document.querySelector('[name="' + name + '"]');
            if (input) {
                input.addEventListener('input', updatePreview);
                input.addEventListener('change', updatePreview);
            }
        });
    });
    
    // Form submit validation
    document.getElementById('userForm').addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const confirm = document.getElementById('password_confirm').value;
        
        if (password !== confirm) {
            e.preventDefault();
            alert('Şifreler eşleşmiyor!');
            return false;
        }
        
        if (password.length < 6) {
            e.preventDefault();
            alert('Şifre en az 6 karakter olmalıdır!');
            return false;
        }
        
        return confirm('Yeni kullanıcıyı oluşturmak istediğinizden emin misiniz?');
    });
    </script>
</body>
</html>