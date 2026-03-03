<?php
// config/db.php

class Database {
    private $host = "localhost";
    private $port = "5432";
    private $db_name = "cazacom";
    private $username = "postgres";   // ⚠️ change if different
    private $password = "StrongPassword!"; // ⚠️ set this'pass' 
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->db_name}";
            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $exception) {
            echo json_encode([
                "status" => "error",
                "message" => "PostgreSQL connection failed",
                "details" => $exception->getMessage()
            ]);
            exit;
        }

        return $this->conn;
    }
}

