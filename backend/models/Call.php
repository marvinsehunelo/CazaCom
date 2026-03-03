<?php
class Call {
    private $db;

    public function __construct($db){
        $this->db = $db;
    }

    public function startCall($user_id, $recipient_number, $duration_minutes){
        // Deduct credits
        $stmt = $this->db->prepare("SELECT balance FROM wallets WHERE user_id=?");
        $stmt->execute([$user_id]);
        $balance = $stmt->fetchColumn();

        $cost_per_min = 1; // Example: 1 unit per minute
        $total_cost = $duration_minutes * $cost_per_min;

        if ($balance < $total_cost) return ['status'=>false, 'message'=>'Insufficient balance'];

        $stmt = $this->db->prepare("UPDATE wallets SET balance=balance-? WHERE user_id=?");
        $stmt->execute([$total_cost, $user_id]);

        // Log transaction
        $stmt = $this->db->prepare("INSERT INTO transactions (user_id, type, amount, status, created_at, description) VALUES (?, 'call', ?, 'completed', NOW(), ?)");
        $stmt->execute([$user_id, $total_cost, "Call to $recipient_number for $duration_minutes minutes"]);

        return ['status'=>true, 'message'=>"Call started. $total_cost units deducted"];
    }
}
