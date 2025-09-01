<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webimvar - Giriş Yap</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #667eea, #764ba2); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { max-width: 400px; background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .logo { text-align: center; font-size: 2rem; color: #667eea; margin-bottom: 2rem; font-weight: bold; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; color: #333; font-weight: 500; }
        input { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 1rem; transition: border-color 0.3s; }
        input:focus { outline: none; border-color: #667eea; }
        .btn { width: 100%; padding: 12px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer; margin-top: 1rem; transition: transform 0.3s; }
        .btn:hover { transform: translateY(-2px); }
        .link { text-align: center; margin-top: 1rem; }
        .link a { color: #667eea; text-decoration: none; }
        .error { color: #e74c3c; margin-top: 1rem; text-align: center; padding: 10px; background: #fdf2f2; border-radius: 5px; }
        .success { color: #27ae60; margin-top: 1rem; text-align: center; padding: 10px; background: #f2fdf2; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">webimvar</div>
        <form id="loginForm">
            <div class="form-group">
                <label>Email Adresiniz</label>
                <input type="email" id="email" required>
            </div>
            <div class="form-group">
                <label>Şifreniz</label>
                <input type="password" id="password" required>
            </div>
            <button type="submit" class="btn">Giriş Yap</button>
            <div class="error" id="error" style="display:none;"></div>
        </form>
        <div class="link">
            <a href="register.php">Hesabınız yok mu? Kayıt olun</a><br>
            <a href="../">Ana Sayfaya Dön</a>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const errorDiv = document.getElementById('error');
            
            try {
                const response = await fetch('../api/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ email, password })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = 'dashboard.php';
                } else {
                    errorDiv.textContent = data.error;
                    errorDiv.style.display = 'block';
                }
            } catch (error) {
                errorDiv.textContent = 'Bir hata oluştu, tekrar deneyin';
                errorDiv.style.display = 'block';
            }
        });
    </script>
</body>
</html>