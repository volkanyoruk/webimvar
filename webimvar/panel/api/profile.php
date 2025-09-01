<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Giriş yapmanız gerekli'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

// Subdomain oluştur (isimden)
$name = $input['name'] ?? '';
$subdomain = strtolower(str_replace(' ', '', transliterateToAscii($name)));

// Subdomain benzersizliği kontrolü
$counter = 1;
$originalSubdomain = $subdomain;
while (true) {
    $stmt = $pdo->prepare("SELECT id FROM user_profiles WHERE subdomain = ? AND user_id != ?");
    $stmt->execute([$subdomain, $userId]);
    
    if (!$stmt->fetch()) {
        break; // Benzersiz subdomain bulundu
    }
    
    $subdomain = $originalSubdomain . $counter;
    $counter++;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO user_profiles 
        (user_id, name, profession, phone, email, bio, company_name, services, working_hours, address, subdomain, site_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        profession = VALUES(profession),
        phone = VALUES(phone),
        bio = VALUES(bio),
        company_name = VALUES(company_name),
        services = VALUES(services),
        working_hours = VALUES(working_hours),
        address = VALUES(address),
        site_status = 'creating',
        updated_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->execute([
        $userId,
        $input['name'] ?? '',
        $input['profession'] ?? '',
        $input['phone'] ?? '',
        $input['email'] ?? '',
        $input['bio'] ?? '',
        $input['company_name'] ?? '',
        $input['services'] ?? '',
        $input['working_hours'] ?? '',
        $input['address'] ?? '',
        $subdomain
    ]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Profil kaydedildi! Siteniz 48 saat içinde hazır olacak.',
        'subdomain' => $subdomain,
        'site_url' => 'https://' . $subdomain . '.webimvar.com'
    ]);
    
} catch (Exception $e) {
    jsonResponse(['error' => 'Profil kaydetme hatası: ' . $e->getMessage()], 500);
}

// Türkçe karakter çevirme fonksiyonu
function transliterateToAscii($str) {
    $tr = array('ş','Ş','ı','I','İ','ğ','Ğ','ü','Ü','ö','Ö','Ç','ç',' ');
    $en = array('s','S','i','I','I','g','G','u','U','o','O','C','c','');
    return str_replace($tr, $en, $str);
}
?>