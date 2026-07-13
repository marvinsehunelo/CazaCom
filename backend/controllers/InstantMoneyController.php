<?php
// controllers/InstantMoneyController.php

require_once __DIR__ . "/../models/User.php";
require_once __DIR__ . "/../models/Wallet.php";
require_once __DIR__ . "/SmsController.php";

class InstantMoneyController {
    private $db;
    private $smsController;
    private $feeAmount;

    public function __construct($db) {
        $this->db = $db;
        $this->smsController = new SmsController($db);
        $this->feeAmount = 3.00; // fee for sender
    }

    // Create voucher & send SMS
    public function createVoucher($sender_id, $recipient_phone, $amount, $channel='DASHBOARD') {
        // --- Check sender balance ---
        $stmt = $this->db->prepare("SELECT balance FROM wallets WHERE user_id=?");
        $stmt->execute([$sender_id]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$wallet || $wallet['balance'] < ($amount + $this->feeAmount)) {
            return ['status'=>'error','message'=>'Insufficient balance'];
        }

        // --- Deduct amount + fee ---
        $stmt = $this->db->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id=?");
        $stmt->execute([$amount + $this->feeAmount, $sender_id]);

        // --- Generate voucher & PIN ---
        $voucher = strtoupper(substr(bin2hex(random_bytes(4)),0,8));
        $pin = random_int(100000,999999);

        // --- Insert transaction ---
        $stmt = $this->db->prepare("
            INSERT INTO instant_money_transactions
            (sender_id, recipient_phone, voucher_code, pin_code, amount, fee, status, channel)
            VALUES (?, ?, ?, ?, ?, ?, 'PENDING', ?)
        ");
        $stmt->execute([$sender_id, $recipient_phone, $voucher, $pin, $amount, $this->feeAmount, $channel]);

        // --- Send SMS to recipient ---
        $this->smsController->sendSms($recipient_phone, "You received P$amount voucher $voucher PIN $pin");

        // --- Send SMS to CazaCom internal number for logging ---
        $this->smsController->sendSms("CazaComInternalNumber", "Voucher $voucher of P$amount sent to $recipient_phone");

        // --- Notify ZuruBank via internal API ---
        $payload = [
            'voucher'=>$voucher,
            'pin'=>$pin,
            'amount'=>$amount,
            'recipient_phone'=>$recipient_phone,
            'sender_id'=>$sender_id
        ];

        $ch = curl_init("https://zurubank.local/api/instant_money.php?action=notify_instant_money");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $resp = curl_exec($ch);
        curl_close($ch);

        return ['status'=>'success','voucher'=>$voucher,'pin'=>$pin,'amount'=>$amount,'recipient'=>$recipient_phone,'zurubank_response'=>$resp];
    }

    /**
     * Send Instant Money
     */
    public function sendInstantMoney($userId, $recipient_phone, $amount, $pin) {
        // Validate input
        if (empty($recipient_phone) || empty($amount) || empty($pin)) {
            return ["status" => "error", "message" => "Recipient, amount, and PIN are required"];
        }
        
        if ($amount <= 0) {
            return ["status" => "error", "message" => "Amount must be greater than 0"];
        }
        
        // Verify PIN
        $stmt = $this->db->prepare("SELECT pin FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !password_verify($pin, $user['pin'])) {
            return ["status" => "error", "message" => "Invalid PIN"];
        }
        
        // Process instant money transfer
        $result = $this->createVoucher($userId, $recipient_phone, $amount, 'API');
        
        return $result;
    }

    /**
     * Redeem Instant Money
     */
    public function redeemInstantMoney($token, $recipient_phone) {
        // Find the voucher
        $stmt = $this->db->prepare("
            SELECT id, sender_id, amount, voucher_code, pin_code, status 
            FROM instant_money_transactions 
            WHERE voucher_code = :token AND status = 'PENDING'
        ");
        $stmt->execute([':token' => $token]);
        $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$voucher) {
            return ["status" => "error", "message" => "Invalid or expired voucher"];
        }
        
        // Mark as redeemed
        $stmt = $this->db->prepare("
            UPDATE instant_money_transactions 
            SET status = 'REDEEMED', redeemed_at = NOW() 
            WHERE id = :id
        ");
        $stmt->execute([':id' => $voucher['id']]);
        
        return [
            "status" => "success", 
            "message" => "Instant money redeemed successfully",
            "amount" => $voucher['amount'],
            "sender_id" => $voucher['sender_id']
        ];
    }

    /**
     * Get Instant Money History - FIXED: Added this method
     */
    public function getInstantMoneyHistory($userId) {
        if (!$userId || $userId <= 0) {
            return ["status" => "error", "message" => "Valid user ID is required"];
        }
        
        try {
            // Get user's phone number
            $stmt = $this->db->prepare("SELECT phone_number FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $userPhone = $user['phone_number'] ?? '';
            
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    sender_id,
                    recipient_phone,
                    voucher_code,
                    pin_code,
                    amount,
                    fee,
                    status,
                    channel,
                    created_at,
                    redeemed_at
                FROM instant_money_transactions
                WHERE sender_id = :user_id 
                   OR recipient_phone = :phone
                ORDER BY created_at DESC
                LIMIT 50
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':phone' => $userPhone
            ]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format the response
            $formattedHistory = [];
            foreach ($history as $record) {
                $formattedHistory[] = [
                    'id' => $record['id'],
                    'sender_id' => $record['sender_id'],
                    'recipient_phone' => $record['recipient_phone'],
                    'voucher_code' => $record['voucher_code'],
                    'amount' => $record['amount'],
                    'fee' => $record['fee'],
                    'status' => $record['status'],
                    'channel' => $record['channel'],
                    'created_at' => $record['created_at'],
                    'redeemed_at' => $record['redeemed_at']
                ];
            }
            
            return [
                "status" => "success",
                "history" => $formattedHistory,
                "count" => count($formattedHistory)
            ];
        } catch (Exception $e) {
            error_log("[InstantMoneyController] Error fetching history: " . $e->getMessage());
            return [
                "status" => "error",
                "message" => "Failed to fetch instant money history: " . $e->getMessage()
            ];
        }
    }
}
