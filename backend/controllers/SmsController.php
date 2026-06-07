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
     * Send SMS - FIXED parameter order
     * @param string $recipientNumber - The phone number to send to
     * @param string $message - The message content
     * @param int $userId - Optional user ID (default 0 for system)
     */
    public function sendSms($recipientNumber, $message, $userId = 0) {
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
            $recipientNumber,   // target_number
            $message,           // message
            0,                  // cost
            'sent'              // direction
        );

        // Find recipient user (if exists)
        $stmt = $this->db->prepare("SELECT id, phone_number FROM users WHERE phone_number = ?");
        $stmt->execute([$recipientNumber]);
        $recipient = $stmt->fetch(PDO::FETCH_ASSOC);

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
     * Fetch SMS history for a user
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

        return ["status" => "success", "history" => $history];
    }
}
