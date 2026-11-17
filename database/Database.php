<?php
/**
 * Database Connection Class
 * 
 * Singleton pattern for database connection management
 */

namespace Database;

use PDO;
use PDOException;

class Database {
    private static $instance = null;
    private $connection;
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $config = require __DIR__ . '/../config/database.php';
        
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );
            
            $this->connection = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options']
            );
            
        } // catch (PDOException $e) {
        //     error_log('Database Connection Error: ' . $e->getMessage());
        //     throw new \Exception('Database connection failed. Please check your configuration.');
        // }
        catch (PDOException $e) {
        die(
        '<pre>' .
        "PDO Error:\n" .
        $e->getMessage() .
        "\n\nDSN:\n" .
        $dsn .
        '</pre>'
    );
}
    }
    
    /**
     * Get database instance (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get PDO connection
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * Alias for getConnection() — used by PageRepository and RetryJobProcessor.
     */
    public function getPdo() {
        return $this->connection;
    }
    
    /**
     * Execute a query and return results as array
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Query Error: ' . $e->getMessage());
            throw new \Exception('Database query failed.');
        }
    }
    
    /**
     * Fetch all results
     */
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params) ?: [];
    }
    
    /**
     * Fetch single result
     */
    public function fetch($sql, $params = []) {
        $results = $this->query($sql, $params);
        return $results ? $results[0] : null;
    }
    
    /**
     * Insert record and return last insert ID
     */
    public function insert($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $this->connection->lastInsertId();
        } catch (PDOException $e) {
            error_log('Query Error: ' . $e->getMessage());
            throw new \Exception('Database query failed.');
        }
    }
    
    /**
     * Update/Delete and return affected rows
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log('Query Error: ' . $e->getMessage());
            throw new \Exception('Database query failed.');
        }
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->connection->rollBack();
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new \Exception('Cannot unserialize singleton');
    }
}
