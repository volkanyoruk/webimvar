<?php
/**
 * Enterprise Logger Class
 * Dosya: public_html/core/classes/Logger.php
 */

class Logger {
    private static $levels = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
        'critical' => 4
    ];
    
    /**
     * Log mesajı yazma
     */
    public static function log($message, $channel = 'system', $level = 'info', $context = []) {
        if (!isset(self::$levels[$level])) {
            $level = 'info';
        }
        
        // Log seviyesi kontrolü
        $minLevel = self::$levels[LogConfig::LEVEL];
        if (self::$levels[$level] < $minLevel) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = self::getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $userId = Session::get('user_id', 'Guest');
        
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => strtoupper($level),
            'channel' => $channel,
            'message' => $message,
            'user_id' => $userId,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'context' => $context,
            'memory_usage' => memory_get_usage(true),
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
        ];
        
        // Dosyaya yazma
        self::writeToFile($channel, $logEntry);
        
        // Database'e yazma (kritik loglar için)
        if ($level === 'error' || $level === 'critical') {
            self::writeToDatabase($logEntry);
        }
    }
    
    /**
     * Dosyaya log yazma
     */
    private static function writeToFile($channel, $logEntry) {
        $logFile = LogConfig::CHANNELS[$channel] ?? LOGS_PATH . 'general.log';
        
        // Log klasörünü oluştur
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Log formatı
        $formattedEntry = sprintf(
            "[%s] %s.%s: %s | User: %s | IP: %s | Memory: %s | Time: %.3fs\n",
            $logEntry['timestamp'],
            strtoupper($logEntry['level']),
            strtoupper($logEntry['channel']),
            $logEntry['message'],
            $logEntry['user_id'],
            $logEntry['ip'],
            self::formatBytes($logEntry['memory_usage']),
            $logEntry['execution_time']
        );
        
        // Context varsa ekle
        if (!empty($logEntry['context'])) {
            $formattedEntry .= "Context: " . json_encode($logEntry['context']) . "\n";
        }
        
        // Dosyaya yazma
        file_put_contents($logFile, $formattedEntry, FILE_APPEND | LOCK_EX);
        
        // Dosya boyut kontrolü ve rotasyon
        self::rotateLogFile($logFile);
    }
    
    /**
     * Database'e log yazma
     */
    private static function writeToDatabase($logEntry) {
        try {
            $db = Database::getInstance();
            $db->insert('system_logs', [
                'user_id' => $logEntry['user_id'] === 'Guest' ? null : $logEntry['user_id'],
                'action' => 'log_entry',
                'module' => $logEntry['channel'],
                'description' => $logEntry['message'],
                'ip_address' => $logEntry['ip'],
                'user_agent' => $logEntry['user_agent'],
                'request_data' => json_encode($logEntry['context']),
                'severity' => $logEntry['level'] === 'WARNING' ? 'warning' : strtolower($logEntry['level'])
            ]);
        } catch (Exception $e) {
            // Database log hatası durumunda sadece dosyaya yazalım
        }
    }
    
    /**
     * Log dosyası rotasyonu
     */
    private static function rotateLogFile($logFile) {
        if (!file_exists($logFile)) return;
        
        $maxSize = self::parseSize(LogConfig::MAX_FILE_SIZE);
        if (filesize($logFile) > $maxSize) {
            $timestamp = date('Y-m-d_H-i-s');
            $rotatedFile = $logFile . '.' . $timestamp;
            rename($logFile, $rotatedFile);
            
            // Eski dosyaları temizle
            self::cleanOldLogFiles(dirname($logFile));
        }
    }
    
    /**
     * Eski log dosyalarını temizle
     */
    private static function cleanOldLogFiles($logDir) {
        $files = glob($logDir . '/*.log.*');
        if (count($files) > LogConfig::MAX_FILES) {
            // Tarih sıralaması
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // En eski dosyaları sil
            $toDelete = array_slice($files, 0, count($files) - LogConfig::MAX_FILES);
            foreach ($toDelete as $file) {
                unlink($file);
            }
        }
    }
    
    /**
     * Client IP adresini al
     */
    private static function getClientIP() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                return trim($ips[0]);
            }
        }
        return 'Unknown';
    }
    
    /**
     * Byte formatı
     */
    private static function formatBytes($size) {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        return round($size, 2) . ' ' . $units[$i];
    }
    
    /**
     * Boyut parse etme
     */
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