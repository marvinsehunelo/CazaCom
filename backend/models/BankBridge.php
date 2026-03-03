<?php
// models/BankBridge.php

/**
 * Handles all database interactions for the bank bridging functionality.
 * This includes logging transactions and retrieving transaction history.
 */
class BankBridge {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Logs a new transaction in the database.
     *
     * @param int $userId The ID of the user.
     * @param string $type The transaction type (e.g., 'cashout', 'create-ewallet', 'autobridge').
     * @param float $amount The transaction amount.
     * @param string $status The status of the transaction (e.g., 'pending', 'completed', 'failed').
     * @param string $description A brief description of the transaction.
     * @return bool True on success, false on failure.
     */
    public function logTransaction(int $userId, string $type, float $amount, string $status, string $description): bool {
        $sql = "INSERT INTO bridge_transactions (user_id, type, amount, status, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$userId, $type, $amount, $status, $description]);
        } catch (PDOException $e) {
            // In a real application, you would log this error.
            return false;
        }
    }

    /**
     * Retrieves a list of recent transactions for a user.
     *
     * @param int $userId The ID of the user.
     * @param int $limit The maximum number of transactions to retrieve.
     * @return array An array of transaction records.
     */
    public function getTransactions(int $userId, int $limit = 50): array {
        $sql = "SELECT id, type, amount, status, description, created_at FROM bridge_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
