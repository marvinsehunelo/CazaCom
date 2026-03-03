<?php
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

    // ✅ Send SMS and store for both sender and receiver
    public function sendSms($userId = 0, $recipientNumber, $message) {
        // Determine sender number
        $senderNumber = "SYSTEM";
        if ($userId > 0) {
            $stmt = $this->db->prepare("SELECT phone_number FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) $senderNumber = $user['phone_number'];
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

        // Send through gateway (simulation)
        SmsGateway::sendSms($userId, $recipientNumber, $message);

        return ["status" => "success", "message" => "SMS sent successfully", "gateway" => "SMS delivered to $recipientNumber"];
    }

    // ✅ Fetch SMS history (accurately labeled)
    public function getHistory($userId) {
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
