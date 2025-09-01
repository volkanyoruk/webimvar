<?php
/**
 * Enterprise Session Management (Webimvar)
 * - Güvenli cookie parametreleri (SameSite, HttpOnly, Secure)
 * - IP/UA parmak izi kontrolü
 * - İnaktivite timeout
 * - Periyodik session ID yenileme
 * - Flash mesajlar (prefix: flash_)
 */

class Session
{
    private static bool $started = false;
    private const REGENERATE_INTERVAL = 300; // 5 dk'da bir id yenile (opsiyonel)

    /**
     * Session başlat (idempotent)
     */
    public static function start(): void
    {
        if (self::$started === true && session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // HTTPS algısı (proxy arkasında olabilir)
        $isHttps = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        );

        // Güvenli session cookie parametreleri
        $cookieLifetime = (int)(defined('SecurityConfig::SESSION_TIMEOUT') ? SecurityConfig::SESSION_TIMEOUT : 3600);

        // PHP 7.3+ array parametresiyle ayarlama
        session_set_cookie_params([
            'lifetime' => $cookieLifetime,
            'path'     => '/',
            'domain'   => self::cookieDomain(), // örn: .webimvar.com (gerekirse ayarlayın)
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Strict', // ihtiyaca göre 'Lax' yapabilirsiniz
        ]);

        // Ek güvenlik ayarları
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $isHttps ? '1' : '0');
        ini_set('session.gc_maxlifetime', (string)$cookieLifetime);

        // İsteğe bağlı: özel ad verilebilir
        // session_name('WEBIMVARSESSID');

        session_start();
        self::$started = true;

        // İlk kurulum & güvenlik kontrolleri
        self::bootstrapSecurityState();
        self::validateSession();
        self::maybeRegenerateId();
    }

    /**
     * Cookie domain’ini belirle (subdomain senaryosu için)
     * Örn: panel.webimvar.com ve ops.webimvar.com aynı oturumu paylaşsın istiyorsanız ".webimvar.com" döndürün.
     * Şimdilik null -> mevcut host kullanılır.
     */
    private static function cookieDomain(): ?string
    {
        // İHTİYACA GÖRE AÇIN:
        // return '.webimvar.com';
        return null;
    }

    /**
     * İlk oturum değişkenlerini hazırla
     */
    private static function bootstrapSecurityState(): void
    {
        // Parmak izi
        if (!isset($_SESSION['_ip'])) {
            $_SESSION['_ip'] = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        if (!isset($_SESSION['_ua'])) {
            // UA'yı direkt tutmak yerine hashlemek daha güvenli
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $_SESSION['_ua'] = hash('sha256', $ua);
        }

        // Zaman damgaları
        $_SESSION['_started_at']     = $_SESSION['_started_at']     ?? time();
        $_SESSION['_last_activity']  = $_SESSION['_last_activity']  ?? time();
        $_SESSION['_last_regen_at']  = $_SESSION['_last_regen_at']  ?? time();
    }

    /**
     * Oturum bütünlüğünü doğrula
     */
    private static function validateSession(): void
    {
        // İnaktivite timeout
        $timeout = (int)(defined('SecurityConfig::SESSION_TIMEOUT') ? SecurityConfig::SESSION_TIMEOUT : 3600);
        $now = time();

        if (isset($_SESSION['_last_activity']) && ($now - (int)$_SESSION['_last_activity']) > $timeout) {
            self::destroy();
            self::log('Session timeout oldu', 'security', 'warning');
            return;
        }

        // IP kontrolü (proxy durumları için ortamınıza göre gevşetebilirsiniz)
        $currentIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!empty($_SESSION['_ip']) && $_SESSION['_ip'] !== $currentIp) {
            self::destroy();
            self::log('Session hijacking ihtimali: IP değişti', 'security', 'warning');
            return;
        }

        // UA kontrolü
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $uaHash = hash('sha256', $ua);
        if (!empty($_SESSION['_ua']) && $_SESSION['_ua'] !== $uaHash) {
            self::destroy();
            self::log('Session hijacking ihtimali: UA değişti', 'security', 'warning');
            return;
        }

        // Aktivite zamanını güncelle
        $_SESSION['_last_activity'] = $now;
    }

    /**
     * Belirli aralıklarla session id yenile
     */
    private static function maybeRegenerateId(): void
    {
        $now = time();
        if (!isset($_SESSION['_last_regen_at']) || ($now - (int)$_SESSION['_last_regen_at']) >= self::REGENERATE_INTERVAL) {
            self::regenerate();
            $_SESSION['_last_regen_at'] = $now;
        }
    }

    /**
     * Güvenli ID yenileme
     */
    public static function regenerate(): void
    {
        self::start();
        @session_regenerate_id(true);
    }

    /**
     * Set
     */
    public static function set(string $key, $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Get
     */
    public static function get(string $key, $default = null)
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Has
     */
    public static function has(string $key): bool
    {
        self::start();
        return array_key_exists($key, $_SESSION);
    }

    /**
     * Remove
     */
    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Tüm oturumu sonlandır
     */
    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Tüm session verisini temizle
            $_SESSION = [];

            // Cookie'yi de sil
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
            }

            session_destroy();
        }
        self::$started = false;
    }

    /**
     * Flash mesaj set et (tek kullanımlık)
     */
    public static function flash(string $key, $message): void
    {
        self::set('flash_' . $key, $message);
    }

    /**
     * Flash mesajı al ve sil (DÜZELTİLDİ: flash_ prefix)
     */
    public static function getFlash(string $key)
    {
        self::start();
        $k = 'flash_' . $key;           // <- düzeltme
        $message = $_SESSION[$k] ?? null;
        if (isset($_SESSION[$k])) {
            unset($_SESSION[$k]);
        }
        return $message;
    }

    /**
     * Mevcut session ID
     */
    public static function id(): ?string
    {
        self::start();
        return session_id() ?: null;
    }

    /**
     * Basit log köprüsü (Logger varsa kullan)
     */
    private static function log(string $message, string $channel = 'system', string $level = 'info'): void
    {
        if (class_exists('Logger')) {
            // Logger::log($message, $channel, $level);
            try {
                Logger::log($message, $channel, $level);
            } catch (\Throwable $e) {
                // sessiz geç
            }
        }
    }
}