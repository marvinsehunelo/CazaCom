<?php
// models/Sms.php

class Sms {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Normalize phone number to ensure it has + prefix
     * @param string $phone - The phone number to normalize
     * @return string - Normalized phone number with + prefix
     */
    private function normalizePhoneNumber($phone): string {
        if (empty($phone)) {
            return $phone;
        }
        
        // Remove any spaces, dashes, parentheses
        $phone = preg_replace('/[^0-9+]/', '', trim($phone));
        
        // If it starts with 00, replace with +
        if (strpos($phone, '00') === 0) {
            $phone = '+' . substr($phone, 2);
        }
        
        // If it doesn't start with + and doesn't start with 0 (local format)
        if (strpos($phone, '+') !== 0 && strpos($phone, '0') !== 0) {
            // Assume it's a local number without country code - add +267 for Botswana
            if (strlen($phone) === 8 && is_numeric($phone)) {
                $phone = '+267' . $phone;
            }
        }
        
        // If it starts with 0 (local format), convert to +267
        if (strpos($phone, '0') === 0 && strlen($phone) === 9) {
            $phone = '+267' . substr($phone, 1);
        }
        
        return $phone;
    }

    /**
     * Save an SMS record - FIXED with phone number normalization
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
        
        // ============================================================
        // FIX: Normalize phone numbers to ensure + prefix
        // ============================================================
        $sender_number = $this->normalizePhoneNumber($sender_number);
        $target_number = $this->normalizePhoneNumber($target_number);
        
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
     * Fetch full SMS history (sent + received) - FIXED with normalization
     */
  /**
 * Fetch full SMS history (sent + received) - FIXED to accept both formats
 */
public function getHistory($phone_number) {
    // ============================================================
    // FIX: Search for both + and non+ formats
    // ============================================================
    $phoneWithPlus = (strpos($phone_number, '+') === 0) ? $phone_number : '+' . $phone_number;
    $phoneWithoutPlus = ltrim($phone_number, '+');
    
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
        WHERE sender_number IN (:phone1, :phone2) 
           OR target_number IN (:phone1, :phone2)
        ORDER BY created_at DESC
        LIMIT 100
    ");
    $stmt->execute([
        'phone1' => $phoneWithPlus,
        'phone2' => $phoneWithoutPlus
    ]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Normalize phone numbers in results (always show with +)
    foreach ($results as &$record) {
        if (isset($record['sender_number']) && strpos($record['sender_number'], '+') !== 0) {
            $record['sender_number'] = '+' . $record['sender_number'];
        }
        if (isset($record['target_number']) && strpos($record['target_number'], '+') !== 0) {
            $record['target_number'] = '+' . $record['target_number'];
        }
    }
    
    return $results;
}
}
