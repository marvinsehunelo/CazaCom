<?php
// config/db.php

class Database {
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        // Check if we're on Railway (DATABASE_URL exists)
        $database_url = getenv('DATABASE_URL');
        
        if ($database_url) {
            // Parse Railway's DATABASE_URL
            $db = parse_url($database_url);
            
            $this->host = $db['host'] ?? 'localhost';
            $this->port = $db['port'] ?? '5432';
            $this->db_name = ltrim($db['path'] ?? '/cazacom', '/');
            $this->username = $db['user'] ?? 'postgres';
            $this->password = $db['pass'] ?? '';
        } else {
            // Fallback for local development using your .env_BW style variables
            $this->host = getenv('PG_HOST') ?: 'localhost';
            $this->port = getenv('PG_PORT') ?: '5432';
            $this->db_name = getenv('PG_NAME') ?: 'cazacom';
            $this->username = getenv('PG_USER') ?: 'postgres';
            $this->password = getenv('PG_PASS') ?: 'StrongPassword!';
        }
    }

    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->db_name}";
            
            // Log connection attempt (without password)
            error_log("Connecting to database: host={$this->host}, port={$this->port}, dbname={$this->db_name}, user={$this->username}");
            
            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5 // 5 second timeout
            ]);
            
            error_log("Database connection successful");
            
        } catch (PDOException $exception) {
            // Log the error
            error_log("PostgreSQL connection failed: " . $exception->getMessage());
            
            // Return error as JSON only for API requests
            if (!defined('CONSOLE_MODE')) {
                header('Content-Type: application/json');
                echo json_encode([
                    "status" => "error",
                    "message" => "Database connection failed",
                    "details" => $exception->getMessage()
                ]);
                exit;
            }
        }

        return $this->conn;
    }
}

// Optional: Test connection if this file is run directly
if (php_sapi_name() === 'cli' && !defined('CONSOLE_MODE')) {
    define('CONSOLE_MODE', true);
    $db = new Database();
    $conn = $db->getConnection();
    if ($conn) {
        echo "✅ Database connection successful!\n";
    } else {
        echo "❌ Database connection failed\n";
    }
}
?>
