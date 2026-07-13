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
                $senderNumber = $user['phone_number'];
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
        // ORDER: user_id, sender_number, target_number, message, cost, direction
        $this->smsModel->saveSms(
            $senderUserId,      // user_id (null for system)
            $senderNumber,      // sender_number
            $recipientNumber,   // target_number (normalized with +)
            $message,           // message
            0,                  // cost
            'sent'              // direction
        );

        // Find recipient user (if exists) - use normalized number
        $stmt = $this->db->prepare("SELECT id, phone_number FROM users WHERE phone_number = ?");
        $stmt->execute([$recipientNumber]);
        $recipient = $stmt->fetch(PDO::FETCH_ASSOC);

        // If not found, try without the + prefix (for legacy data)
        if (!$recipient) {
            $phoneWithoutPlus = ltrim($recipientNumber, '+');
            $stmt = $this->db->prepare("SELECT id, phone_number FROM users WHERE phone_number = ? OR phone_number = ?");
            $stmt->execute([$phoneWithoutPlus, $recipientNumber]);
            $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Save "received" record for recipient
        if ($recipient) {
            $this->smsModel->saveSms(
                $recipient['id'],           // user_id
                $senderNumber,              // sender_number
                $recipient['phone_number'], // target_number
                $message,                   // message
                0,                          // cost
                'received'                  // direction
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
     * Fetch SMS history for a user - FIXED to normalize phone numbers in response
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
        $history = $this->smsModel->getHistory($phone);

        // Normalize phone numbers in response
        foreach ($history as &$record) {
            if (isset($record['target_number']) && strpos($record['target_number'], '+') !== 0) {
                $record['target_number'] = $this->normalizePhoneNumber($record['target_number']);
            }
            if (isset($record['sender_number']) && strpos($record['sender_number'], '+') !== 0) {
                $record['sender_number'] = $this->normalizePhoneNumber($record['sender_number']);
            }
        }

        return ["status" => "success", "history" => $history];
    }
}
