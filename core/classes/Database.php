<?php
/**
 * Enterprise Database Class
 * Gelişmiş database yönetimi ve güvenlik özellikleri
 */

class Database {
    private static $instance = null;
    private $connection;
    private $transactionLevel = 0;
    private $queryCount = 0;
    private $queries = [];
    
    private function __construct() {
        $this->connect();
    }
    
    /**
     * Singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Database bağlantısı
     */
    private function connect() {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DatabaseConfig::HOST,
                DatabaseConfig::DATABASE,
                DatabaseConfig::CHARSET
            );
            
            $this->connection = new PDO(
    $dsn,
    DatabaseConfig::USERNAME,
    DatabaseConfig::PASSWORD,
    DatabaseConfig::options() // <-- sabit değil, method
);
            
            Logger::log('Database connection established', 'system');
            
        } catch (PDOException $e) {
            Logger::log('Database connection failed: ' . $e->getMessage(), 'system', 'error');
            throw new Exception('Database connection failed');
        }
    }
    
    /**
     * Sorgu çalıştırma (SELECT)
     */
    public function query($sql, $params = []) {
        $start_time = microtime(true);
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            
            $execution_time = microtime(true) - $start_time;
            $this->logQuery($sql, $params, $execution_time);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            Logger::log("Query error: {$e->getMessage()}, SQL: $sql", 'system', 'error');
            throw new Exception('Database query failed');
        }
    }
    
    /**
     * Tek kayıt getirme
     */
    public function queryOne($sql, $params = []) {
        $start_time = microtime(true);
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            
            $execution_time = microtime(true) - $start_time;
            $this->logQuery($sql, $params, $execution_time);
            
            return $stmt->fetch();
            
        } catch (PDOException $e) {
            Logger::log("Query error: {$e->getMessage()}, SQL: $sql", 'system', 'error');
            throw new Exception('Database query failed');
        }
    }
    
    /**
     * Insert işlemi
     */
    public function insert($table, $data) {
        $fields = implode('`, `', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO `{$table}` (`{$fields}`) VALUES ({$placeholders})";
        
        $start_time = microtime(true);
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($data);
            
            $execution_time = microtime(true) - $start_time;
            $this->logQuery($sql, $data, $execution_time);
            
            return $this->connection->lastInsertId();
            
        } catch (PDOException $e) {
            Logger::log("Insert error: {$e->getMessage()}, SQL: $sql", 'system', 'error');
            throw new Exception('Database insert failed');
        }
    }
    
    /**
     * Update işlemi
     */
    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach (array_keys($data) as $field) {
            $set[] = "`{$field}` = :{$field}";
        }
        $setClause = implode(', ', $set);
        
        $sql = "UPDATE `{$table}` SET {$setClause} WHERE {$where}";
        
        $params = array_merge($data, $whereParams);
        
        $start_time = microtime(true);
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            
            $execution_time = microtime(true) - $start_time;
            $this->logQuery($sql, $params, $execution_time);
            
            return $stmt->rowCount();
            
        } catch (PDOException $e) {
            Logger::log("Update error: {$e->getMessage()}, SQL: $sql", 'system', 'error');
            throw new Exception('Database update failed');
        }
    }
    
    /**
     * Delete işlemi
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM `{$table}` WHERE {$where}";
        
        $start_time = microtime(true);
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            
            $execution_time = microtime(true) - $start_time;
            $this->logQuery($sql, $params, $execution_time);
            
            return $stmt->rowCount();
            
        } catch (PDOException $e) {
            Logger::log("Delete error: {$e->getMessage()}, SQL: $sql", 'system', 'error');
            throw new Exception('Database delete failed');
        }
    }
    
    /**
     * Transaction başlatma
     */
    public function beginTransaction() {
        if ($this->transactionLevel === 0) {
            $this->connection->beginTransaction();
        }
        $this->transactionLevel++;
    }
    
    /**
     * Transaction commit
     */
    public function commit() {
        if ($this->transactionLevel > 0) {
            $this->transactionLevel--;
            if ($this->transactionLevel === 0) {
                $this->connection->commit();
            }
        }
    }
    
    /**
     * Transaction rollback
     */
    public function rollback() {
        if ($this->transactionLevel > 0) {
            $this->transactionLevel = 0;
            $this->connection->rollback();
        }
    }
    
    /**
     * Sorgu loglamak
     */
    private function logQuery($sql, $params, $execution_time) {
        $this->queryCount++;
        
        if (PerformanceConfig::ENABLE_PROFILING) {
            $this->queries[] = [
                'sql' => $sql,
                'params' => $params,
                'execution_time' => $execution_time,
                'memory_usage' => memory_get_usage(true)
            ];
            
            // Yavaş sorguları logla
            if ($execution_time > PerformanceConfig::SLOW_QUERY_THRESHOLD) {
                Logger::log(
                    "Slow query detected: {$execution_time}s - SQL: $sql",
                    'performance',
                    'warning'
                );
            }
        }
    }
    
    /**
     * Performans istatistikleri
     */
    public function getStats() {
        return [
            'query_count' => $this->queryCount,
            'queries' => $this->queries,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
    }
    
    /**
     * Bağlantıyı kapat
     */
    public function close() {
        $this->connection = null;
    }
}

/**
 * Model Base Class - Tüm modeller bu sınıftan türeyecek
 */
abstract class Model {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $hidden = [];
    protected $timestamps = true;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * ID ile kayıt bulma
     */
    public function find($id) {
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id LIMIT 1";
        return $this->db->queryOne($sql, ['id' => $id]);
    }
    
    /**
     * Tüm kayıtları getir
     */
    public function all($limit = null, $offset = 0) {
        $sql = "SELECT * FROM `{$this->table}`";
        if ($limit) {
            $sql .= " LIMIT {$offset}, {$limit}";
        }
        return $this->db->query($sql);
    }
    
    /**
     * Koşula göre kayıt bulma
     */
    public function where($field, $operator, $value) {
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$field}` {$operator} :value";
        return $this->db->query($sql, ['value' => $value]);
    }
    
    /**
     * Tek koşula göre kayıt bulma
     */
    public function findBy($field, $value) {
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$field}` = :value LIMIT 1";
        return $this->db->queryOne($sql, ['value' => $value]);
    }
    
    /**
     * Yeni kayıt oluşturma
     */
    public function create($data) {
        // Sadece fillable alanları al
        if (!empty($this->fillable)) {
            $data = array_intersect_key($data, array_flip($this->fillable));
        }
        
        // Timestamp ekle
        if ($this->timestamps) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        return $this->db->insert($this->table, $data);
    }
    
    /**
     * Kayıt güncelleme
     */
    public function updateById($id, $data) {
        // Sadece fillable alanları al
        if (!empty($this->fillable)) {
            $data = array_intersect_key($data, array_flip($this->fillable));
        }
        
        // Timestamp güncelle
        if ($this->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        return $this->db->update(
            $this->table,
            $data,
            "`{$this->primaryKey}` = :id",
            ['id' => $id]
        );
    }
    
    /**
     * Kayıt silme
     */
    public function deleteById($id) {
        return $this->db->delete(
            $this->table,
            "`{$this->primaryKey}` = :id",
            ['id' => $id]
        );
    }
    
    /**
     * Sayım
     */
    public function count($where = '1=1', $params = []) {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE {$where}";
        $result = $this->db->queryOne($sql, $params);
        return $result['count'];
    }
    
    /**
     * Gizli alanları temizle
     */
    protected function hideFields($data) {
        if (is_array($data) && !empty($this->hidden)) {
            foreach ($this->hidden as $field) {
                unset($data[$field]);
            }
        }
        return $data;
    }
}