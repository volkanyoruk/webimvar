<?php
session_start();
if (isset($_SESSION['admin_id'])) {
    header('Location: panel.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ops Panel - Giriş</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #2c3e50, #34495e); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { max-width: 400px; background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .logo { text-align: center; font-size: 2rem; color: #2c3e50; margin-bottom: 2rem; font-weight: bold; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; color: #333; font-weight: 500; }
        input { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 1rem; }
        input:focus { outline: none; border-color: #2c3e50; }
        .btn { width: 100%; padding: 12px; background: #2c3e50; color: white; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer; margin-top: 1rem; }
        .btn:hover { background: #34495e; }
        .error { color: #e74c3c; margin-top: 1rem; text-align: center; padding: 10px; background: #fdf2f2; border-radius: 5px; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 5px; margin-bottom: 1rem; text-align: center; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🔒 OPS PANEL</div>
        <div class="warning">Yetkisiz erişim yasaktır!</div>
        <form id="loginForm">
            <div class="form-group">
                <label>Kullanıcı Adı</label>
                <input type="text" id="username" required>
            </div>
            <div class="form-group">
                <label>Şifre</label>
                <input type="password" id="password" required>
            </div>
            <button type="submit" class="btn">Giriş Yap</button>
            <div class="error" id="error" style="display:none;"></div>
        </form>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const errorDiv = document.getElementById('error');
            
            try {
                const response = await fetch('api/admin-login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = 'panel.php';
                } else {
                    errorDiv.textContent = data.error;
                    errorDiv.style.display = 'block';
                }
            } catch (error) {
                errorDiv.textContent = 'Bağlantı hatası';
                errorDiv.style.display = 'block';
            }
        });
    </script>
</body>
</html>