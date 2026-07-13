<?php
// controllers/SmsController.php

require_once __DIR__ . "/../models/Sms.php";
require_once __DIR__ . "/../services/SmsGateway.php";

class SmsController {
    private $db;
    private $smsModel;

    public function __construct($db) {
        $this->db = $db;
        $this->smsModel = new Sms($db);
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
     * Send SMS - FIXED with phone number normalization
     * @param string $recipientNumber - The phone number to send to
     * @param string $message - The message content
     * @param int $userId - Optional user ID (default 0 for system)
     */
    public function sendSms($recipientNumber, $message, $userId = 0) {
        // ============================================================
        // FIX: Normalize the recipient number to ensure + prefix
        // ============================================================
        $recipientNumber = $this->normalizePhoneNumber($recipientNumber);
        
        // Determine sender number
        $senderNumber = "SYSTEM";
        $senderUserId = null;
        
        if ($userId > 0) {
            $stmt = $this->db->prepare("SELECT id, phone_number FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $senderNumber = $this->normalizePhoneNumber($user['phone_number']);
                $senderUserId = $user['id'];
            }
        }

        // Validate input
        if (empty($recipientNumber)) {
            return ["status" => "error", "message" => "Recipient number is required"];
        }
        
        if (empty($message)) {
            return ["status" => "error", "message" => "Message content is required"];
        }

        // Save "sent" record for sender
        $this->smsModel->saveSms(
            $senderUserId,
            $senderNumber,
            $recipientNumber,
            $message,
            0,
            'sent'
        );

        // Find recipient user - try both formats
        $recipient = null;
        $phoneWithPlus = (strpos($recipientNumber, '+') === 0) ? $recipientNumber : '+' . $recipientNumber;
        $phoneWithoutPlus = ltrim($recipientNumber, '+');
        
        $stmt = $this->db->prepare("
            SELECT id, phone_number 
            FROM users 
            WHERE phone_number IN (:phone1, :phone2)
               OR phone_number = :phone3
        ");
        $stmt->execute([
            'phone1' => $phoneWithPlus,
            'phone2' => $phoneWithoutPlus,
            'phone3' => $recipientNumber
        ]);
        $recipient = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save "received" record for recipient
        if ($recipient) {
            $this->smsModel->saveSms(
                $recipient['id'],
                $senderNumber,
                $recipient['phone_number'],
                $message,
                0,
                'received'
            );
        }

        return [
            "status" => "success", 
            "message" => "SMS sent successfully",
            "to" => $recipientNumber,
            "from" => $senderNumber
        ];
    }

    /**
     * Fetch SMS history for a user - FIXED to accept both + and non+ formats
     */
    public function getHistory($userId) {
        if (!$userId || $userId <= 0) {
            return ["status" => "error", "message" => "Valid user ID is required"];
        }
        
        $stmt = $this->db->prepare("SELECT phone_number FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ["status" => "error", "message" => "User not found"];
        }

        $phone = $user['phone_number'];
        $phoneWithPlus = (strpos($phone, '+') === 0) ? $phone : '+' . $phone;
        $phoneWithoutPlus = ltrim($phone, '+');
        
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
        
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Normalize phone numbers in response (always show with +)
        foreach ($history as &$record) {
            if (isset($record['sender_number']) && strpos($record['sender_number'], '+') !== 0) {
                $record['sender_number'] = '+' . $record['sender_number'];
            }
            if (isset($record['target_number']) && strpos($record['target_number'], '+') !== 0) {
                $record['target_number'] = '+' . $record['target_number'];
            }
        }

        return ["status" => "success", "history" => $history];
    }
}
