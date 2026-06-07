<?php
// models/Sms.php

class Sms {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Save an SMS record
     * @param int|null $user_id - User ID (can be null for system)
     * @param string $sender_number - Sender's phone number  
     * @param string $target_number - Recipient's phone number
     * @param string $message - SMS content
     * @param float $cost - Cost of SMS (default 0)
     * @param string $direction - 'sent' or 'received'
     */
    public function saveSms($user_id, $sender_number, $target_number, $message, $cost = 0, $direction = 'sent') {
        // Handle null user_id
        if (empty($user_id) || $user_id === 0) {
            $user_id = null;
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO sms (user_id, sender_number, target_number, message, cost, direction, created_at)
            VALUES (:user_id, :sender, :target, :message, :cost, :direction, NOW())
        ");
        
        return $stmt->execute([
            ':user_id' => $user_id,
            ':sender' => $sender_number,
            ':target' => $target_number,
            ':message' => $message,
            ':cost' => $cost,
            ':direction' => $direction
        ]);
    }

    /**
     * Fetch full SMS history (sent + received)
     */
    public function getHistory($phone_number) {
        $stmt = $this->db->prepare("
            SELECT 
                id,
                sender_number,
                target_number,
                message,
                cost,
                direction,
                created_at
            FROM sms
            WHERE sender_number = :phone OR target_number = :phone
            ORDER BY created_at DESC
            LIMIT 100
        ");
        $stmt->execute(['phone' => $phone_number]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
