<?php
/**
 * Database Connection Class
 * Handles MySQL database connections using PDO
 */

class Database {
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $charset;
    private $connection;

    public function __construct() {
        // Check if constants are defined before using them
        if (!defined('DB_HOST')) {
            throw new Exception('Database configuration constants not defined. Make sure config.php is included first.');
        }
        
        $this->host = DB_HOST;
        $this->dbname = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        $this->charset = DB_CHARSET;
    }

    /**
     * Create database connection
     * @return PDO
     */
    public function connect() {
        $this->connection = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->dbname . ";charset=" . $this->charset;
            $this->connection = new PDO($dsn, $this->username, $this->password);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }

        return $this->connection;
    }

    /**
     * Close database connection
     */
    public function disconnect() {
        $this->connection = null;
    }

    /**
     * Get connection instance
     * @return PDO
     */
    public function getConnection() {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }
}
?>
