<?php
class InstantMoney {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($senderId, $recipientPhone, $amount, $token) {
        $stmt = $this->conn->prepare("
            INSERT INTO instant_money (sender_id, sender_phone, recipient_phone, amount, token, status, created_at)
            VALUES (
                :sender_id,
                (SELECT phone FROM users WHERE id = :sender_id),
                :recipient_phone,
                :amount,
                :token,
                'pending',
                NOW()
            )
        ");
        $stmt->execute([
            ':sender_id' => $senderId,
            ':recipient_phone' => $recipientPhone,
            ':amount' => $amount,
            ':token' => $token
        ]);
    }

    public function findByToken($token) {
        $stmt = $this->conn->prepare("SELECT * FROM instant_money WHERE token = :token LIMIT 1");
        $stmt->execute([':token' => $token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function markRedeemed($token) {
        $stmt = $this->conn->prepare("UPDATE instant_money SET status='redeemed', redeemed_at=NOW() WHERE token=:token");
        $stmt->execute([':token' => $token]);
    }

    public function getHistory($userId) {
        $stmt = $this->conn->prepare("
            SELECT * FROM instant_money
            WHERE sender_id = :userId OR recipient_phone = (SELECT phone FROM users WHERE id = :userId)
            ORDER BY created_at DESC
        ");
        $stmt->execute([':userId' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
