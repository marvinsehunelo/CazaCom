<?php
class Wallet {
    private $db;
    private $table = 'wallets';

    public function __construct($db) {
        $this->db = $db;
    }

    public function getWalletByUserId($user_id) {
        $stmt = $this->db->prepare("SELECT balance, saccus_ewallet_balance, credit_balance FROM {$this->table} WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateBalance($user_id, $amount, $column = 'balance') {
        if (!in_array($column, ['balance', 'saccus_ewallet_balance', 'credit_balance'])) {
            throw new Exception("Invalid balance column");
        }
        $stmt = $this->db->prepare("UPDATE {$this->table} SET {$column} = ? WHERE user_id = ?");
        return $stmt->execute([$amount, $user_id]);
    }

    public function logTransaction($user_id, $type, $amount, $description) {
        $stmt = $this->db->prepare("INSERT INTO transactions (user_id, type, amount, status, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        return $stmt->execute([$user_id, $type, $amount, 'completed', $description]);
    }
}
?>
