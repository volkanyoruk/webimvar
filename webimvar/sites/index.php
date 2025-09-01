<?php
// Alt domain handler - tüm *.webimvar.com isteklerini karşılar
require_once '../api/config.php';

// Subdomain'i al
$host = $_SERVER['HTTP_HOST'];
$subdomain = explode('.', $host)[0];

// Ana domain kontrolü
if ($subdomain === 'webimvar' || $subdomain === 'www') {
    header('Location: https://webimvar.com');
    exit;
}

// Panel kontrolü
if ($subdomain === 'panel') {
    header('Location: https://panel.webimvar.com');
    exit;
}

try {
    // Kullanıcı bilgilerini al
    $stmt = $pdo->prepare("
        SELECT up.*, u.status as user_status
        FROM user_profiles up 
        JOIN users u ON up.user_id = u.id 
        WHERE up.subdomain = ? AND u.status = 'active'
    ");
    $stmt->execute([$subdomain]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        // 404 sayfası
        show404();
        exit;
    }
    
    // Site durumu kontrolü
    if ($userData['site_status'] === 'pending' || $userData['site_status'] === 'creating') {
        showPending($userData);
        exit;
    }
    
    // Site oluştur ve göster
    generateSite($userData);
    
} catch (Exception $e) {
    http_response_code(500);
    echo "<h1>Site yükleme hatası</h1>";
    echo "<p>Bir hata oluştu, lütfen daha sonra tekrar deneyin.</p>";
}

function show404() {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Site Bulunamadı - Webimvar</title>
        <style>
            body { font-family: 'Segoe UI', sans-serif; margin: 0; background: #f8f9fa; min-height: 100vh; display: flex; align-items: center; justify-content: center; text-align: center; color: #333; }
            .container { max-width: 500px; padding: 2rem; }
            h1 { font-size: 4rem; color: #667eea; margin-bottom: 1rem; }
            p { font-size: 1.2rem; margin-bottom: 2rem; }
            .btn { display: inline-block; background: #667eea; color: white; padding: 12px 24px; border-radius: 25px; text-decoration: none; transition: background 0.3s; }
            .btn:hover { background: #5a6fd8; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>404</h1>
            <p>Bu site bulunamadı veya henüz oluşturulmadı.</p>
            <a href="https://webimvar.com" class="btn">Ana Sayfaya Dön</a>
        </div>
    </body>
    </html>
    <?php
}

function showPending($userData) {
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Site Hazırlanıyor - <?php echo htmlspecialchars($userData['name']); ?></title>
        <style>
            body { font-family: 'Segoe UI', sans-serif; margin: 0; background: linear-gradient(135deg, #667eea, #764ba2); color: white; min-height: 100vh; display: flex; align-items: center; justify-content: center; text-align: center; }
            .container { max-width: 500px; padding: 2rem; }
            h1 { font-size: 3rem; margin-bottom: 1rem; }
            p { font-size: 1.2rem; margin-bottom: 2rem; opacity: 0.9; }
            .spinner { width: 50px; height: 50px; border: 5px solid rgba(255,255,255,0.3); border-top: 5px solid white; border-radius: 50%; animation: spin 1s linear infinite; margin: 2rem auto; }
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            .btn { display: inline-block; background: rgba(255,255,255,0.2); color: white; padding: 12px 24px; border-radius: 25px; text-decoration: none; margin-top: 1rem; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🚧</h1>
            <h1><?php echo htmlspecialchars($userData['name']); ?></h1>
            <div class="spinner"></div>
            <p><strong><?php echo htmlspecialchars($userData['profession']); ?></strong></p>
            <p>Sitemiz şu anda hazırlanıyor.<br>48 saat içinde yayına alınacak.</p>
            <a href="https://panel.webimvar.com" class="btn">Panele Git</a>
        </div>
    </body>
    </html>
    <?php
}

function generateSite($data) {
    $galleryImages = $data['gallery_images'] ? json_decode($data['gallery_images'], true) : [];
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($data['name']); ?> - <?php echo htmlspecialchars($data['profession']); ?></title>
        <meta name="description" content="<?php echo htmlspecialchars($data['bio']); ?>">
        
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Segoe UI', sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 1000px; margin: 0 auto; padding: 0 20px; }
            
            /* Header */
            .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 4rem 0; text-align: center; }
            .profile-photo { 
                width: 150px; height: 150px; border-radius: 50%; margin: 0 auto 2rem; 
                background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; 
                font-size: 3rem; color: white; font-weight: bold;
                <?php if($data['profile_image']): ?>
                background-image: url('<?php echo htmlspecialchars($data['profile_image']); ?>'); 
                background-size: cover; background-position: center;
                <?php endif; ?>
            }
            .header h1 { font-size: 2.5rem; margin-bottom: 0.5rem; }
            .header p { font-size: 1.2rem; opacity: 0.9; }
            
            /* Sections */
            .section { padding: 3rem 0; }
            .section:nth-child(even) { background: #f8f9fa; }
            .section-title { text-align: center; font-size: 2rem; margin-bottom: 2rem; color: #2d3748; }
            
            /* About */
            .about-content { text-align: center; font-size: 1.1rem; max-width: 600px; margin: 0 auto; }
            
            /* Services */
            .services { white-space: pre-line; font-size: 1rem; max-width: 800px; margin: 0 auto; }
            
            /* Contact */
            .contact-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-top: 2rem; }
            .contact-item { text-align: center; padding: 2rem 1rem; background: white; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
            .contact-icon { font-size: 2.5rem; margin-bottom: 1rem; }
            .contact-item h3 { margin-bottom: 1rem; color: #2d3748; }
            .contact-item a { color: #667eea; text-decoration: none; font-weight: 500; }
            .contact-item a:hover { text-decoration: underline; }
            
            /* Gallery */
            .gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; margin-top: 2rem; }
            .gallery img { width: 100%; height: 200px; object-fit: cover; border-radius: 8px; transition: transform 0.3s; cursor: pointer; }
            .gallery img:hover { transform: scale(1.05); }
            
            /* Footer */
            .footer { background: #2d3748; color: white; padding: 2rem 0; text-align: center; }
            .footer a { color: #667eea; text-decoration: none; }
            
            /* Responsive */
            @media (max-width: 768px) {
                .header h1 { font-size: 2rem; }
                .profile-photo { width: 120px; height: 120px; font-size: 2rem; }
                .contact-grid { grid-template-columns: 1fr; }
                .gallery { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); }
                .gallery img { height: 150px; }
                .section { padding: 2rem 0; }
            }
        </style>
    </head>
    <body>
        <!-- Header -->
        <section class="header">
            <div class="container">
                <div class="profile-photo">
                    <?php if(!$data['profile_image']) echo strtoupper(substr($data['name'], 0, 1)); ?>
                </div>
                <h1><?php echo htmlspecialchars($data['name']); ?></h1>
                <p><?php echo htmlspecialchars($data['profession']); ?></p>
                <?php if($data['company_name']): ?>
                    <p style="margin-top: 0.5rem; opacity: 0.8;"><?php echo htmlspecialchars($data['company_name']); ?></p>
                <?php endif; ?>
            </div>
        </section>
        
        <?php if($data['bio']): ?>
        <!-- About -->
        <section class="section">
            <div class="container">
                <h2 class="section-title">Hakkımda</h2>
                <div class="about-content">
                    <p><?php echo nl2br(htmlspecialchars($data['bio'])); ?></p>
                </div>
            </div>
        </section>
        <?php endif; ?>
        
        <?php if($data['services']): ?>
        <!-- Services -->
        <section class="section">
            <div class="container">
                <h2 class="section-title">Hizmetlerim</h2>
                <div class="services">
                    <?php echo nl2br(htmlspecialchars($data['services'])); ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
        
        <?php if(!empty($galleryImages)): ?>
        <!-- Gallery -->
        <section class="section">
            <div class="container">
                <h2 class="section-title">Galeri</h2>
                <div class="gallery">
                    <?php foreach($galleryImages as $image): ?>
                        <img src="<?php echo htmlspecialchars($image); ?>" alt="Galeri" loading="lazy" onclick="openImage(this.src)">
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
        
        <!-- Contact -->
        <section class="section">
            <div class="container">
                <h2 class="section-title">İletişim</h2>
                <div class="contact-grid">
                    <?php if($data['phone']): ?>
                    <div class="contact-item">
                        <div class="contact-icon">📞</div>
                        <h3>Telefon</h3>
                        <p><a href="tel:<?php echo $data['phone']; ?>"><?php echo htmlspecialchars($data['phone']); ?></a></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="contact-item">
                        <div class="contact-icon">📧</div>
                        <h3>Email</h3>
                        <p><a href="mailto:<?php echo $data['email']; ?>"><?php echo htmlspecialchars($data['email']); ?></a></p>
                    </div>
                    
                    <?php if($data['working_hours']): ?>
                    <div class="contact-item">
                        <div class="contact-icon">🕒</div>
                        <h3>Çalışma Saatleri</h3>
                        <p><?php echo htmlspecialchars($data['working_hours']); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($data['address']): ?>
                    <div class="contact-item">
                        <div class="contact-icon">📍</div>
                        <h3>Adres</h3>
                        <p><?php echo htmlspecialchars($data['address']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        
        <!-- Footer -->
        <footer class="footer">
            <div class="container">
                <p>&copy; 2025 <?php echo htmlspecialchars($data['name']); ?> - <a href="https://webimvar.com" target="_blank">webimvar.com</a> ile oluşturuldu</p>
            </div>
        </footer>
        
        <script>
            function openImage(src) {
                window.open(src, '_blank');
            }
        </script>
    </body>
    </html>
    <?php
}
?>