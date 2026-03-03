<?php
class Transaction {
    public $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function create($user_id, $type, $amount, $description = "", $status = "success") {
        $stmt = $this->db->prepare("
            INSERT INTO transactions (user_id, type, amount, description, status, created_at)
            VALUES (:user_id, :type, :amount, :description, :status, NOW())
        ");
        return $stmt->execute([
            ":user_id"     => $user_id,
            ":type"        => $type,
            ":amount"      => $amount,
            ":description" => $description,
            ":status"      => $status
        ]);
    }

    public function getByUser($user_id) {
        $stmt = $this->db->prepare("
            SELECT id, type, amount, description, status, created_at
            FROM transactions
            WHERE user_id = :user_id
            ORDER BY created_at DESC
        ");
        $stmt->execute([":user_id" => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
