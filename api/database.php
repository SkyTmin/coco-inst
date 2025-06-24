<?php
/**
 * Database connection handler with singleton pattern
 */

class Database {
    private static $instance = null;
    private $connection = null;
    private $transactionCount = 0;
    
    private function __construct() {
        try {
            $dsn = getDatabaseDSN();
            $this->connection = new PDO($dsn, DB_USER, DB_PASS);
            
            // Set error mode
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Set fetch mode
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Additional MySQL specific settings
            if (DB_TYPE === 'mysql') {
                $this->connection->exec("SET NAMES utf8mb4");
                $this->connection->exec("SET CHARACTER SET utf8mb4");
                $this->connection->exec("SET SESSION sql_mode = 'TRADITIONAL'");
            }
            
            // Additional PostgreSQL specific settings
            if (DB_TYPE === 'pgsql') {
                $this->connection->exec("SET TIME ZONE 'UTC'");
            }
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    /**
     * Get singleton instance
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
     * Begin transaction with support for nested transactions
     */
    public function beginTransaction() {
        if ($this->transactionCount === 0) {
            $this->connection->beginTransaction();
        } else {
            $this->connection->exec('SAVEPOINT trans' . $this->transactionCount);
        }
        $this->transactionCount++;
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        $this->transactionCount--;
        if ($this->transactionCount === 0) {
            $this->connection->commit();
        } else {
            $this->connection->exec('RELEASE SAVEPOINT trans' . $this->transactionCount);
        }
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        $this->transactionCount--;
        if ($this->transactionCount === 0) {
            $this->connection->rollback();
        } else {
            $this->connection->exec('ROLLBACK TO SAVEPOINT trans' . $this->transactionCount);
        }
    }
    
    /**
     * Execute query with parameters
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query execution failed: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("Query execution failed");
        }
    }
    
    /**
     * Fetch single row
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Fetch all rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get last insert ID
     */
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    /**
     * Create database tables if they don't exist
     */
    public function createTables() {
        try {
            // Users table
            $this->execute("
                CREATE TABLE IF NOT EXISTS users (
                    id " . (DB_TYPE === 'pgsql' ? 'SERIAL' : 'INT AUTO_INCREMENT') . " PRIMARY KEY,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    last_login TIMESTAMP NULL,
                    is_active BOOLEAN DEFAULT TRUE,
                    failed_login_attempts INT DEFAULT 0,
                    locked_until TIMESTAMP NULL
                )
            ");
            
            // Refresh tokens table
            $this->execute("
                CREATE TABLE IF NOT EXISTS refresh_tokens (
                    id " . (DB_TYPE === 'pgsql' ? 'SERIAL' : 'INT AUTO_INCREMENT') . " PRIMARY KEY,
                    user_id INT NOT NULL,
                    token VARCHAR(255) UNIQUE NOT NULL,
                    expires_at TIMESTAMP NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");
            
            // Coco Money sheets table
            $this->execute("
                CREATE TABLE IF NOT EXISTS coco_money_sheets (
                    id " . (DB_TYPE === 'pgsql' ? 'SERIAL' : 'INT AUTO_INCREMENT') . " PRIMARY KEY,
                    user_id INT NOT NULL,
                    data " . (DB_TYPE === 'pgsql' ? 'JSONB' : 'JSON') . " NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");
            
            // Coco Money categories table
            $this->execute("
                CREATE TABLE IF NOT EXISTS coco_money_categories (
                    id " . (DB_TYPE === 'pgsql' ? 'SERIAL' : 'INT AUTO_INCREMENT') . " PRIMARY KEY,
                    user_id INT NOT NULL,
                    categories " . (DB_TYPE === 'pgsql' ? 'JSONB' : 'JSON') . " NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");
            
            // Debts table
            $this->execute("
                CREATE TABLE IF NOT EXISTS debts (
                    id " . (DB_TYPE === 'pgsql' ? 'SERIAL' : 'INT AUTO_INCREMENT') . " PRIMARY KEY,
                    user_id INT NOT NULL,
                    debts " . (DB_TYPE === 'pgsql' ? 'JSONB' : 'JSON') . " NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");
            
            // Debt categories table
            $this->execute("
                CREATE TABLE IF NOT EXISTS debt_categories (
                    id " . (DB_TYPE === 'pgsql' ? 'SERIAL' : 'INT AUTO_INCREMENT') . " PRIMARY KEY,
                    user_id INT NOT NULL,
                    categories " . (DB_TYPE === 'pgsql' ? 'JSONB' : 'JSON') . " NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");
            
            // Clothing size table
            $this->execute("
                CREATE TABLE IF NOT EXISTS clothing_size (
                    id " . (DB_TYPE === 'pgsql' ? 'SERIAL' : 'INT AUTO_INCREMENT') . " PRIMARY KEY,
                    user_id INT NOT NULL UNIQUE,
                    data " . (DB_TYPE === 'pgsql' ? 'JSONB' : 'JSON') . " NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");
            
            // Scale calculator history table
            $this->execute("
                CREATE TABLE IF NOT EXISTS scale_calculator_history (
                    id " . (DB_TYPE === 'pgsql' ? 'SERIAL' : 'INT AUTO_INCREMENT') . " PRIMARY KEY,
                    user_id INT NOT NULL,
                    history " . (DB_TYPE === 'pgsql' ? 'JSONB' : 'JSON') . " NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");
            
            // Create indexes
            $this->execute("CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)");
            $this->execute("CREATE INDEX IF NOT EXISTS idx_refresh_tokens_token ON refresh_tokens(token)");
            $this->execute("CREATE INDEX IF NOT EXISTS idx_refresh_tokens_user_id ON refresh_tokens(user_id)");
            
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to create tables: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}