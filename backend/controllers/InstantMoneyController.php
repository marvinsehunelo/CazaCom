<?php
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
        $this->smsController->sendSms(0, $recipient_phone, "You received P$amount voucher $voucher PIN $pin");

        // --- Send SMS to CazaCom internal number for logging ---
        $this->smsController->sendSms(0, "CazaComInternalNumber", "Voucher $voucher of P$amount sent to $recipient_phone");

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
}
