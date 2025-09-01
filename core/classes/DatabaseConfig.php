<?php
/**
 * Database Configuration Class
 * Updated with real database credentials
 * Supports both old and new constant formats
 */
class DatabaseConfig {
    
    // New format constants (for Database.php compatibility)
    public const HOST      = 'localhost';
    public const USERNAME  = 'webimvar_volo';
    public const PASSWORD  = '904dqKO%IMM+KxQ!';
    public const DATABASE  = 'webimvar_enterprise';
    public const CHARSET   = 'utf8mb4';
    
    // Old format constants (for backward compatibility)
    public const DB_HOST     = 'localhost';
    public const DB_USER     = 'webimvar_volo';
    public const DB_PASS     = '904dqKO%IMM+KxQ!';
    public const DB_NAME     = 'webimvar_enterprise';
    public const DB_CHARSET  = 'utf8mb4';
    
    /**
     * Database bağlantı bilgilerini dizi olarak döndür
     */
    public static function getConfig() {
        return [
            'host' => self::HOST,
            'dbname' => self::DATABASE,
            'username' => self::USERNAME,
            'password' => self::PASSWORD,
            'charset' => self::CHARSET
        ];
    }
    
    /**
     * DSN string oluştur
     */
    public static function getDSN() {
        return "mysql:host=" . self::HOST . ";dbname=" . self::DATABASE . ";charset=" . self::CHARSET;
    }
    
    /**
     * Backward compatibility metodları
     */
    public static function getHost() { return self::HOST; }
    public static function getDbName() { return self::DATABASE; }
    public static function getUsername() { return self::USERNAME; }
    public static function getPassword() { return self::PASSWORD; }
    public static function getCharset() { return self::CHARSET; }
}
?>