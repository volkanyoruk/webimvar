<?php
// Kullanıcı Düzenleme Sayfası
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

// Paketleri getir
$packages = [];
try {
    $packages_query = "SELECT id, name, price FROM packages ORDER BY price ASC";
    $packages = $pdo->query($packages_query)->fetchAll();
} catch (Exception $e) {
    // Packages tablosu yok
}

$message = '';
$error = '';

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $full_name = $_POST['full_name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $role = $_POST['role'] ?? 'user';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $package_id = !empty($_POST['package_id']) ? (int)$_POST['package_id'] : null;
        $new_password = $_POST['new_password'] ?? '';

        // Validation
        if (empty($username)) {
            throw new Exception("Kullanıcı adı gerekli.");
        }
        if (empty($email)) {
            throw new Exception("Email adresi gerekli.");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Geçerli bir email adresi girin.");
        }

        // Email benzersizliği kontrolü (kendi ID'si hariç)
        $email_check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $email_check->execute([$email, $user_id]);
        if ($email_check->fetch()) {
            throw new Exception("Bu email adresi başka bir kullanıcı tarafından kullanılıyor.");
        }

        // Kullanıcı adı benzersizliği kontrolü (kendi ID'si hariç)
        $username_check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $username_check->execute([$username, $user_id]);
        if ($username_check->fetch()) {
            throw new Exception("Bu kullanıcı adı başka bir kullanıcı tarafından kullanılıyor.");
        }

        $pdo->beginTransaction();

        // Temel bilgileri güncelle
        $update_fields = [];
        $update_values = [];

        // Hangi alanların var olduğunu kontrol et
        $columns = [];
        $column_result = $pdo->query("DESCRIBE users");
        while($row = $column_result->fetch()) {
            $columns[] = $row['Field'];
        }

        if (in_array('username', $columns)) {
            $update_fields[] = "username = ?";
            $update_values[] = $username;
        }
        if (in_array('email', $columns)) {
            $update_fields[] = "email = ?";
            $update_values[] = $email;
        }
        if (in_array('full_name', $columns)) {
            $update_fields[] = "full_name = ?";
            $update_values[] = $full_name;
        }
        if (in_array('phone', $columns)) {
            $update_fields[] = "phone = ?";
            $update_values[] = $phone;
        }
        if (in_array('role', $columns)) {
            $update_fields[] = "role = ?";
            $update_values[] = $role;
        }
        if (in_array('is_active', $columns)) {
            $update_fields[] = "is_active = ?";
            $update_values[] = $is_active;
        }
        if (in_array('package_id', $columns) && $package_id) {
            $update_fields[] = "package_id = ?";
            $update_values[] = $package_id;
        }
        if (in_array('updated_at', $columns)) {
            $update_fields[] = "updated_at = NOW()";
        }

        // Şifre değiştirilecek mi?
        if (!empty($new_password)) {
            if (strlen($new_password) < 6) {
                throw new Exception("Şifre en az 6 karakter olmalı.");
            }
            $update_fields[] = "password = ?";
            $update_values[] = password_hash($new_password, PASSWORD_DEFAULT);
        }

        if (!empty($update_fields)) {
            $update_values[] = $user_id;
            $update_sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute($update_values);
        }

        $pdo->commit();
        $message = "Kullanıcı bilgileri başarıyla güncellendi.";

        // Güncellenmiş kullanıcı bilgilerini getir
        $user_stmt->execute([$user_id]);
        $user = $user_stmt->fetch();

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
    <title>Kullanıcı Düzenle - <?= htmlspecialchars($user['username'] ?? $user['email'] ?? 'Kullanıcı') ?></title>
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
        .btn-danger { background: #dc3545; color: white; }
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
        
        .user-preview { background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .user-avatar { width: 50px; height: 50px; border-radius: 50%; background: #007bff; color: white; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 15px; }
        .user-info { display: inline-flex; flex-direction: column; }
        
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
            <h1>Kullanıcı Düzenle</h1>
            <p>Kullanıcı bilgilerini güncelleyin</p>
        </div>

        <div class="form-card">
            <!-- Kullanıcı Önizleme -->
            <div class="user-preview">
                <div style="display: flex; align-items: center;">
                    <div class="user-avatar">
                        <?= strtoupper(substr($user['username'] ?? $user['email'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="user-info">
                        <strong><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'Kullanıcı') ?></strong>
                        <small><?= htmlspecialchars($user['email'] ?? '') ?> | ID: <?= $user['id'] ?> | Kayıt: <?= date('d.m.Y', strtotime($user['created_at'] ?? 'now')) ?></small>
                    </div>
                </div>
            </div>

            <form method="POST">
                <!-- Temel Bilgiler -->
                <div class="form-section">
                    <h2>Temel Bilgiler</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Kullanıcı Adı <span class="required">*</span></label>
                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
                            <div class="help-text">Benzersiz kullanıcı adı</div>
                        </div>
                        <div class="form-group">
                            <label>Email Adresi <span class="required">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                            <div class="help-text">Giriş için kullanılacak email</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tam İsim</label>
                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>">
                            <div class="help-text">İsim ve soyisim</div>
                        </div>
                        <div class="form-group">
                            <label>Telefon</label>
                            <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                            <div class="help-text">İletişim telefonu</div>
                        </div>
                    </div>
                </div>

                <!-- Hesap Ayarları -->
                <div class="form-section">
                    <h2>Hesap Ayarları</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Rol</label>
                            <select name="role" class="select-control">
                                <option value="user" <?= ($user['role'] ?? 'user') == 'user' ? 'selected' : '' ?>>Kullanıcı</option>
                                <option value="admin" <?= ($user['role'] ?? '') == 'admin' ? 'selected' : '' ?>>Yönetici</option>
                                <option value="manager" <?= ($user['role'] ?? '') == 'manager' ? 'selected' : '' ?>>Müdür</option>
                                <option value="support" <?= ($user['role'] ?? '') == 'support' ? 'selected' : '' ?>>Destek</option>
                            </select>
                            <div class="help-text">Kullanıcının sistem yetkisi</div>
                        </div>
                        
                        <?php if (!empty($packages)): ?>
                        <div class="form-group">
                            <label>Paket</label>
                            <select name="package_id" class="select-control">
                                <option value="">Paket Seçilmedi</option>
                                <?php foreach ($packages as $package): ?>
                                    <option value="<?= $package['id'] ?>" <?= ($user['package_id'] ?? '') == $package['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($package['name']) ?> (<?= number_format($package['price']) ?>₺/ay)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="help-text">Kullanıcının hosting paketi</div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-row single">
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" name="is_active" id="is_active" <?= ($user['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label for="is_active">Hesap Aktif</label>
                            </div>
                            <div class="help-text">Pasif hesaplar giriş yapamaz</div>
                        </div>
                    </div>
                </div>

                <!-- Şifre Değiştirme -->
                <div class="form-section">
                    <h2>Şifre Değiştirme</h2>
                    
                    <div class="form-row single">
                        <div class="form-group">
                            <label>Yeni Şifre</label>
                            <input type="password" name="new_password" id="new_password" class="form-control" onkeyup="checkPasswordStrength(this.value)">
                            <div class="help-text">Boş bırakırsanız şifre değişmez. En az 6 karakter.</div>
                            <div class="password-strength" id="passwordStrength" style="display: none;">
                                <div class="strength-bar">
                                    <div class="strength-fill" id="strengthFill"></div>
                                </div>
                                <small id="strengthText"></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Aksiyonları -->
                <div class="form-actions">
                    <div>
                        <button type="submit" class="btn btn-success">Değişiklikleri Kaydet</button>
                        <a href="user-detail.php?id=<?= $user_id ?>" class="btn btn-primary">Kullanıcı Detayı</a>
                    </div>
                    <div>
                        <a href="users.php" class="btn btn-secondary">İptal Et</a>
                        <a href="users.php?delete=<?= $user_id ?>" class="btn btn-danger" onclick="return confirm('Bu kullanıcıyı silmek istediğinizden emin misiniz?')">Kullanıcıyı Sil</a>
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
    
    // Form submit confirmation
    document.querySelector('form').addEventListener('submit', function(e) {
        const newPassword = document.getElementById('new_password').value;
        if (newPassword && newPassword.length < 6) {
            e.preventDefault();
            alert('Şifre en az 6 karakter olmalıdır.');
            return false;
        }
        
        return confirm('Değişiklikleri kaydetmek istediğinizden emin misiniz?');
    });
    </script>
</body>
</html>