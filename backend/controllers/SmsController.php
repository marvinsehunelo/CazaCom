<?php
// controllers/SmsController.php

require_once __DIR__ . "/../models/Sms.php";
require_once __DIR__ . "/../services/SmsGateway.php";

class SmsController {
    private $db;
    private $walletController;
    private $smsModel;

    public function __construct($db, $walletController = null) {
        $this->db = $db;
        $this->walletController = $walletController;
        $this->smsModel = new Sms($db);
    }

    /**
     * Send SMS - Fixed parameter order
     * @param string $recipientNumber - The phone number to send to
     * @param string $message - The message content
     * @param int $userId - Optional user ID (default 0 for system)
     */
    public function sendSms($recipientNumber, $message, $userId = 0) {
        // Determine sender number
        $senderNumber = "SYSTEM";
        if ($userId > 0) {
            $stmt = $this->db->prepare("SELECT phone_number FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) $senderNumber = $user['phone_number'];
        }

        // Validate input
        if (empty($recipientNumber)) {
            return ["status" => "error", "message" => "Recipient number is required"];
        }
        
        if (empty($message)) {
            return ["status" => "error", "message" => "Message content is required"];
        }

        // Save "sent" record for sender
        $this->smsModel->saveSms($userId, $senderNumber, $recipientNumber, $message, 0, 'sent');

        // Find recipient user (if exists)
        $stmt = $this->db->prepare("SELECT id, phone_number FROM users WHERE phone_number = ?");
        $stmt->execute([$recipientNumber]);
        $recipient = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save "received" record for recipient
        if ($recipient) {
            $this->smsModel->saveSms($recipient['id'], $senderNumber, $recipient['phone_number'], $message, 0, 'received');
        }

        // Send through gateway
        $gatewayResult = SmsGateway::sendSms($userId, $recipientNumber, $message);

        return [
            "status" => "success", 
            "message" => "SMS sent successfully", 
            "gateway" => "SMS delivered to $recipientNumber",
            "gateway_response" => $gatewayResult
        ];
    }

    /**
     * Send SMS with authentication (for API routes)
     * @param int $userId - Authenticated user ID
     * @param array $data - Request data containing recipient_number and message
     */
    public function sendSmsWithAuth($userId, $data) {
        $recipientNumber = $data['recipient_number'] ?? null;
        $message = $data['message'] ?? null;
        
        if (!$recipientNumber || !$message) {
            return ["status" => "error", "message" => "Missing recipient_number or message"];
        }
        
        return $this->sendSms($recipientNumber, $message, $userId);
    }

    /**
     * Fetch SMS history for a user
     * @param int $userId - The user ID
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
    
    /**
     * Get SMS inbox for a user
     * @param int $userId - The user ID
     */
    public function getInbox($userId) {
        if (!$userId || $userId <= 0) {
            return ["status" => "error", "message" => "Valid user ID is required"];
        }
        
        $stmt = $this->db->prepare("
            SELECT id, sender_number, target_number, message, cost, created_at 
            FROM sms 
            WHERE user_id = ? AND direction = 'received'
            ORDER BY created_at DESC 
            LIMIT 100
        ");
        $stmt->execute([$userId]);
        $inbox = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return ["status" => "success", "inbox" => $inbox];
    }
    
    /**
     * Get SMS outbox for a user
     * @param int $userId - The user ID
     */
    public function getOutbox($userId) {
        if (!$userId || $userId <= 0) {
            return ["status" => "error", "message" => "Valid user ID is required"];
        }
        
        $stmt = $this->db->prepare("
            SELECT id, sender_number, target_number, message, cost, created_at 
            FROM sms 
            WHERE user_id = ? AND direction = 'sent'
            ORDER BY created_at DESC 
            LIMIT 100
        ");
        $stmt->execute([$userId]);
        $outbox = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return ["status" => "success", "outbox" => $outbox];
    }
}
