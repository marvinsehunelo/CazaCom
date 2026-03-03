<?php
class Sms {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    // Save an SMS record
    public function saveSms($user_id, $sender_number, $target_number, $message, $cost, $direction = 'sent') {
        $stmt = $this->db->prepare("
            INSERT INTO sms (user_id, sender_number, target_number, message, cost, direction, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id ?: null, $sender_number, $target_number, $message, $cost, $direction]);
    }

    // Fetch full SMS history (sent + received)
    public function getHistory($phone_number) {
        $stmt = $this->db->prepare("
            SELECT 
                id,
                sender_number,
                target_number,
                message,
                cost,
                created_at,
                CASE 
                    WHEN sender_number = :phone THEN 'sent'
                    ELSE 'received'
                END AS direction
            FROM sms
            WHERE sender_number = :phone OR target_number = :phone
            ORDER BY created_at DESC
        ");
        $stmt->execute(['phone' => $phone_number]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
