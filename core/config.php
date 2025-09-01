<?php
/**
 * Webimvar Enterprise Panel - Core Configuration (FIXED)
 * path: public_html/core/config.php
 */

/* 1) Güvenlik guard */
if (!defined('ENTERPRISE_PANEL') && !defined('WEBIMVAR_ENTERPRISE')) {
    http_response_code(403);
    exit('Erişim reddedildi!');
}

/* 2) Yol sabitleri */
define('ROOT_PATH', dirname(__DIR__));
define('CORE_PATH', __DIR__ . '/');
define('OPS_PATH', ROOT_PATH . '/ops/');
define('PANEL_PATH', ROOT_PATH . '/panel/');
define('UPLOADS_PATH', ROOT_PATH . '/uploads/');
define('LOGS_PATH', ROOT_PATH . '/logs/');
define('CACHE_PATH', ROOT_PATH . '/cache/');
define('ASSETS_PATH', ROOT_PATH . '/assets/');

/* 3) Uygulama bilgisi */
define('APP_NAME', 'Webimvar Enterprise Panel');
define('APP_VERSION', '1.0.0');
if (!defined('APP_ENVIRONMENT')) {
    define('APP_ENVIRONMENT', 'production');
}

/* 4) URL sabitleri */
if (!defined('BASE_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'webimvar.com';
    define('BASE_URL', $scheme . '://' . $host . '/');
}
if (!defined('OPS_URL'))   define('OPS_URL', BASE_URL . 'ops/');
if (!defined('PANEL_URL')) define('PANEL_URL', 'https://panel.webimvar.com/');
if (!defined('ASSETS_URL')) define('ASSETS_URL', BASE_URL . 'assets/');

/* 5) Database yapılandırması - DÜZELTİLDİ */
class DatabaseConfig {
    public const HOST      = 'localhost';
    public const USERNAME  = 'webimvar_volo';
    public const PASSWORD  = '904dqKO%IMM+KxQ!';
    public const DATABASE  = 'webimvar_enterprise';
    public const CHARSET   = 'utf8mb4';
    public const COLLATION = 'utf8mb4_unicode_ci';

    // ⭐ PDO options as static method
    public static function options(): array {
        if (!class_exists('PDO')) return [];
        return [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        ];
    }
}

/* 6) Security yapılandırması */
class SecurityConfig {
    public const ENCRYPTION_KEY      = 'webimvar2024enterprise32char!!';
    public const JWT_SECRET          = 'jwt-secret-key-webimvar-2024-enterprise';
    public const JWT_EXPIRE          = 3600;
    public const PASSWORD_MIN_LENGTH = 8;
    public const MAX_LOGIN_ATTEMPTS  = 5;
    public const LOCKOUT_DURATION    = 300;
    public const SESSION_TIMEOUT     = 3600;
    public const CSRF_TOKEN_EXPIRE   = 3600;
    public const API_RATE_LIMIT      = 1000;
}

/* 7) E-posta yapılandırması */
class EmailConfig {
    public const SMTP_HOST   = 'mail.webimvar.com';
    public const SMTP_PORT   = 587;
    public const SMTP_USERNAME = 'noreply@webimvar.com';
    public const SMTP_PASSWORD = 'mail_password_here';
    public const SMTP_ENCRYPTION = 'tls';
    public const FROM_EMAIL  = 'noreply@webimvar.com';
    public const FROM_NAME   = 'Webimvar Enterprise Panel';
    public const ADMIN_EMAIL = 'admin@webimvar.com';
}

/* 8) Cache yapılandırması */
class CacheConfig {
    public const DRIVER     = 'file';
    public const REDIS_HOST = 'localhost';
    public const REDIS_PORT = 6379;
    public const DEFAULT_TTL= 3600;
    public const PREFIX     = 'webimvar_enterprise_';
}

/* 9) Log yapılandırması */
class LogConfig {
    public const LEVEL          = 'info';
    public const MAX_FILES      = 30;
    public const MAX_FILE_SIZE  = '10MB';
    public const CHANNELS       = [
        'system'      => LOGS_PATH . 'system.log',
        'security'    => LOGS_PATH . 'security.log',
        'api'         => LOGS_PATH . 'api.log',
        'performance' => LOGS_PATH . 'performance.log',
    ];
}

/* 10) Sistem ayarları */
class SystemConfig {
    public const TIMEZONE        = 'Europe/Istanbul';
    public const LOCALE          = 'tr_TR';
    public const DATE_FORMAT     = 'd.m.Y';
    public const TIME_FORMAT     = 'H:i:s';
    public const DATETIME_FORMAT = 'd.m.Y H:i:s';
    public const PAGINATION_LIMIT= 25;
    public const MAX_UPLOAD_SIZE = '50MB';
    public const ALLOWED_EXTENSIONS = ['jpg','jpeg','png','gif','pdf','doc','docx'];
}

/* 10.5) Performans ayarları */
class PerformanceConfig {
    public const ENABLE_PROFILING       = true;
    public const SLOW_QUERY_THRESHOLD   = 1.0;
    public const MEMORY_LIMIT_WARNING   = '128MB';
    public const EXECUTION_TIME_WARNING = 5.0;
}

/* 11) Hata işleme */
error_reporting(APP_ENVIRONMENT === 'production' ? 0 : E_ALL);
ini_set('display_errors', APP_ENVIRONMENT === 'production' ? '0' : '1');
ini_set('log_errors', '1');
if (!is_dir(LOGS_PATH)) @mkdir(LOGS_PATH, 0755, true);
ini_set('error_log', LOGS_PATH . 'php_errors.log');

/* 12) Saat dilimi */
date_default_timezone_set(SystemConfig::TIMEZONE);

/* 13) Güvenlik başlıkları */
if (!headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

/* 14) Basit autoloader */
spl_autoload_register(function ($class) {
    $paths = [
        CORE_PATH  . 'classes/',
        OPS_PATH   . 'models/',
        OPS_PATH   . 'controllers/',
        PANEL_PATH . 'controllers/',
    ];
    $classPath = str_replace('\\','/',$class) . '.php';
    foreach ($paths as $path) {
        $file = $path . $classPath;
        if (is_file($file)) { require_once $file; return; }
    }
});

/* 15) Gerekli klasörler */
@is_dir(CACHE_PATH)   || @mkdir(CACHE_PATH, 0755, true);
@is_dir(UPLOADS_PATH) || @mkdir(UPLOADS_PATH, 0755, true);

/* 16) Composer autoload */
$composer = ROOT_PATH . '/vendor/autoload.php';
if (is_file($composer)) { require_once $composer; }