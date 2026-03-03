<?php

class Session {
    private PDO $conn;
    private string $table_name = "sessions";

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /**
     * Cleans up expired sessions (runs automatically before new insert).
     */
    private function cleanExpired(): void {
        try {
            $stmt = $this->conn->prepare("DELETE FROM {$this->table_name} WHERE expires_at <= NOW()");
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Failed to clean expired sessions: " . $e->getMessage());
        }
    }

    /**
     * Creates a new session record for the user, replacing old ones.
     */
    public function create(int $userId, string $token): bool {
        $this->cleanExpired(); // ✅ Clean expired before creating new

        $expiresAt = date('Y-m-d H:i:s', time() + (7 * 24 * 60 * 60)); 
        $lastActivity = date('Y-m-d H:i:s');

        try {
            // Optional: enforce one session per user
            $delete = $this->conn->prepare("DELETE FROM {$this->table_name} WHERE user_id = :user_id");
            $delete->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $delete->execute();

            // Insert new session
            $stmt = $this->conn->prepare("
                INSERT INTO {$this->table_name} (user_id, token, expires_at, last_activity)
                VALUES (:user_id, :token, :expires_at, :last_activity)
            ");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':token', $token, PDO::PARAM_STR);
            $stmt->bindParam(':expires_at', $expiresAt, PDO::PARAM_STR);
            $stmt->bindParam(':last_activity', $lastActivity, PDO::PARAM_STR);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Session creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Finds an active session by token. Used for API authentication.
     */
    public function findByToken(string $token): array|false {
        $this->cleanExpired(); // ✅ Clean before lookup

        $query = "
            SELECT id, user_id, token, expires_at 
            FROM {$this->table_name}
            WHERE token = :token AND expires_at > NOW()
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":token", $token);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: false;
    }

    /**
     * Deletes a session record by token (for logout).
     */
    public function delete(string $token): bool {
        $query = "DELETE FROM {$this->table_name} WHERE token = :token";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":token", $token);
        return $stmt->execute();
    }
}
