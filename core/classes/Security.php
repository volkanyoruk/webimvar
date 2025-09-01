<?php
/**
 * Enterprise Security Class
 * Dosya: public_html/core/classes/Security.php
 */

class Security {
    
    /**
     * Şifre hashleme
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterasyon
            'threads' => 3,         // 3 thread
        ]);
    }
    
    /**
     * Şifre doğrulama
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Güvenli random token üretimi
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * CSRF token üretimi
     */
    public static function generateCSRFToken() {
        $token = self::generateToken(32);
        Session::set('csrf_token', $token);
        Session::set('csrf_token_time', time());
        return $token;
    }
    
    /**
     * CSRF token doğrulama
     */
    public static function verifyCSRFToken($token) {
        $sessionToken = Session::get('csrf_token');
        $tokenTime = Session::get('csrf_token_time');
        
        if (!$sessionToken || !$tokenTime) {
            return false;
        }
        
        // Token süresi kontrolü
        if (time() - $tokenTime > SecurityConfig::CSRF_TOKEN_EXPIRE) {
            return false;
        }
        
        return hash_equals($sessionToken, $token);
    }
    
    /**
     * XSS temizleme
     */
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * SQL Injection temizleme (ek güvenlik)
     */
    public static function sanitizeSQL($input) {
        return addslashes($input);
    }
    
    /**
     * Email doğrulama
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Güçlü şifre kontrolü
     */
    public static function validatePassword($password) {
        $errors = [];
        
        if (strlen($password) < SecurityConfig::PASSWORD_MIN_LENGTH) {
            $errors[] = "Şifre en az " . SecurityConfig::PASSWORD_MIN_LENGTH . " karakter olmalıdır";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Şifre en az bir küçük harf içermelidir";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Şifre en az bir büyük harf içermelidir";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Şifre en az bir rakam içermelidir";
        }
        
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = "Şifre en az bir özel karakter içermelidir";
        }
        
        return empty($errors) ? true : $errors;
    }
    
    /**
     * IP adresini yasaklı listesinde kontrol et
     */
    public static function isIPBlacklisted($ip) {
        // Database'den yasaklı IP'leri kontrol et
        return false;
    }
    
    /**
     * Rate limiting kontrolü
     */
    public static function checkRateLimit($identifier, $maxRequests = 100, $timeWindow = 3600) {
        $cacheKey = 'rate_limit_' . $identifier;
        
        // Basit dosya tabanlı rate limiting
        $cacheFile = CACHE_PATH . md5($cacheKey) . '.cache';
        
        $currentTime = time();
        $requests = 0;
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if ($data && $data['expires'] > $currentTime) {
                $requests = $data['requests'];
            }
        }
        
        if ($requests >= $maxRequests) {
            Logger::log("Rate limit exceeded for: $identifier", 'security', 'warning');
            return false;
        }
        
        // Request sayısını artır
        $data = [
            'requests' => $requests + 1,
            'expires' => $currentTime + $timeWindow
        ];
        
        file_put_contents($cacheFile, json_encode($data), LOCK_EX);
        return true;
    }
    
    /**
     * Güvenli dosya yükleme kontrolü
     */
    public static function validateFileUpload($file) {
        $errors = [];
        
        // Dosya boyutu kontrolü
        $maxSize = self::parseSize(SystemConfig::MAX_UPLOAD_SIZE);
        if ($file['size'] > $maxSize) {
            $errors[] = "Dosya boyutu çok büyük";
        }
        
        // Dosya uzantısı kontrolü
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, SystemConfig::ALLOWED_EXTENSIONS)) {
            $errors[] = "Dosya uzantısına izin verilmiyor";
        }
        
        // MIME type kontrolü
        $allowedMimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf'
        ];
        
        if (isset($allowedMimes[$extension])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if ($mimeType !== $allowedMimes[$extension]) {
                $errors[] = "Dosya türü ile uzantısı uyuşmuyor";
            }
        }
        
        return empty($errors) ? true : $errors;
    }
    
    /**
     * JWT Token üretimi
     */
    public static function generateJWT($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload['exp'] = time() + SecurityConfig::JWT_EXPIRE;
        $payload = json_encode($payload);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, SecurityConfig::JWT_SECRET, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    /**
     * JWT Token doğrulama
     */
    public static function verifyJWT($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        
        $header = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[0]));
        $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1]));
        $signature = str_replace(['-', '_'], ['+', '/'], $parts[2]);
        
        $expectedSignature = hash_hmac('sha256', $parts[0] . "." . $parts[1], SecurityConfig::JWT_SECRET, true);
        $expectedSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($expectedSignature));
        
        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }
        
        $payload = json_decode($payload, true);
        if ($payload['exp'] < time()) {
            return false; // Token süresi dolmuş
        }
        
        return $payload;
    }
    
    private static function parseSize($size) {
        $unit = strtoupper(substr($size, -2));
        $value = (int) substr($size, 0, -2);
        
        switch ($unit) {
            case 'GB': return $value * 1024 * 1024 * 1024;
            case 'MB': return $value * 1024 * 1024;
            case 'KB': return $value * 1024;
            default: return $value;
        }
    }
}