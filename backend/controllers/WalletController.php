<?php
require_once __DIR__ . "/../models/Wallet.php";
require_once __DIR__ . "/../models/User.php";
require_once __DIR__ . "/SmsController.php";

class WalletController {
    private $db;
    private $smsController;
    private $feeAmount;

    public function __construct($db) {
        $this->db = $db;
        $this->smsController = new SmsController($db, $this);
        $this->feeAmount = 1.00; // Fee applied only for eWallet transfers
    }

    // Get wallet balances
    public function balance($userId) {
        $stmt = $this->db->prepare("SELECT balance, credit_balance, saccus_ewallet_balance FROM wallets WHERE user_id = ?");
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$wallet) return ["status"=>"error","message"=>"Wallet not found"];
        return [
            "status"=>"success",
            "balance"=>$wallet['balance'],
            "credit_balance"=>$wallet['credit_balance'],
            "saccus_ewallet_balance"=>$wallet['saccus_ewallet_balance']
        ];
    }

    // Deposit to balance
    public function deposit($userId, $amount) {
        $stmt = $this->db->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
        $stmt->execute([$amount, $userId]);
        return ["status"=>"success","message"=>"Deposit successful","balance"=>$this->balance($userId)['balance']];
    }

    // Transfer from credit_balance to balance (Free)
    public function creditToBalance($amount, $userId) {
        $stmt = $this->db->prepare("SELECT balance, credit_balance FROM wallets WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$wallet) return ["status"=>"error","message"=>"Wallet not found"];
        if ($wallet['credit_balance'] < $amount) return ["status"=>"error","message"=>"Insufficient credit balance"];

        $newCredit = $wallet['credit_balance'] - $amount;
        $newBalance = $wallet['balance'] + $amount;

        $stmt = $this->db->prepare("UPDATE wallets SET credit_balance = ?, balance = ? WHERE user_id = ?");
        $stmt->execute([$newCredit, $newBalance, $userId]);

        return ["status"=>"success","message"=>"Credit transferred to balance","balance"=>$newBalance,"credit_balance"=>$newCredit];
    }

    // Transfer from balance to credit_balance (Free)
    public function balanceToCredit($amount, $userId) {
        $stmt = $this->db->prepare("SELECT balance, credit_balance FROM wallets WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$wallet) return ["status"=>"error","message"=>"Wallet not found"];
        if ($wallet['balance'] < $amount) return ["status"=>"error","message"=>"Insufficient balance"];

        $newBalance = $wallet['balance'] - $amount;
        $newCredit = $wallet['credit_balance'] + $amount;

        $stmt = $this->db->prepare("UPDATE wallets SET balance = ?, credit_balance = ? WHERE user_id = ?");
        $stmt->execute([$newBalance, $newCredit, $userId]);

        return ["status"=>"success","message"=>"Balance transferred to credit","balance"=>$newBalance,"credit_balance"=>$newCredit];
    }

    // Transfer from Saccus eWallet to phone balance (Fee applied)
    public function ewalletToBalance($bankUserId, $phone, $amount) {
        // --- Deduct from bank eWallet ---
        $stmt = $this->db->prepare("SELECT saccus_ewallet_balance FROM wallets WHERE user_id = ? LIMIT 1");
        $stmt->execute([$bankUserId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$wallet) return ["status"=>"error","message"=>"Bank wallet not found"];
        if ($wallet['saccus_ewallet_balance'] < ($amount + $this->feeAmount)) return ["status"=>"error","message"=>"Insufficient bank eWallet balance"];

        $newSaccus = $wallet['saccus_ewallet_balance'] - ($amount + $this->feeAmount);
        $stmt = $this->db->prepare("UPDATE wallets SET saccus_ewallet_balance = ? WHERE user_id = ?");
        $stmt->execute([$newSaccus, $bankUserId]);

        // --- Credit phone balance ---
        $stmt = $this->db->prepare("UPDATE wallets SET balance = balance + ? WHERE phone = ?");
        $stmt->execute([$amount, $phone]);

        // --- Route fee: half to bank fee account, half to CazaCom fee account ---
        $stmt = $this->db->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
        $stmt->execute([$this->feeAmount/2, 42]); // Bank fee account
        $stmt = $this->db->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
        $stmt->execute([$this->feeAmount/2, 52]); // CazaCom fee account

        // --- Notify via SMS ---
        $this->smsController->sendSms(0, $phone, "You received P$amount from Saccus eWallet");

        return ["status"=>"success","message"=>"eWallet transfer successful","saccus_ewallet_balance"=>$newSaccus,"phone"=>$phone,"amount"=>$amount];
    }

    // Transfer from phone balance to Saccus eWallet (Fee applied)
    public function balanceToEwallet($userId, $amount) {
        // --- Get wallet ---
        $stmt = $this->db->prepare("SELECT balance, saccus_ewallet_balance FROM wallets WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$wallet) return ["status"=>"error","message"=>"Wallet not found"];
        if ($wallet['balance'] < ($amount + $this->feeAmount)) return ["status"=>"error","message"=>"Insufficient balance for transfer + fee"];

        // --- Deduct balance + fee ---
        $newBalance = $wallet['balance'] - ($amount + $this->feeAmount);
        $newSaccus = $wallet['saccus_ewallet_balance'] + $amount;

        $stmt = $this->db->prepare("UPDATE wallets SET balance = ?, saccus_ewallet_balance = ? WHERE user_id = ?");
        $stmt->execute([$newBalance, $newSaccus, $userId]);

        // --- Route fee ---
        $stmt = $this->db->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
        $stmt->execute([$this->feeAmount/2, 42]); // Bank fee account
        $stmt = $this->db->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
        $stmt->execute([$this->feeAmount/2, 52]); // CazaCom fee account

        return ["status"=>"success","message"=>"Balance transferred to Saccus eWallet","balance"=>$newBalance,"saccus_ewallet_balance"=>$newSaccus];
    }

    // USSD Transfer from phone (requires PIN)
    public function ussdTransfer($phone, $amount, $pin) {
        $stmt = $this->db->prepare("SELECT u.id, w.credit_balance, w.balance FROM users u JOIN wallets w ON u.id=w.user_id WHERE phone_number=? AND pin=? LIMIT 1");
        $stmt->execute([$phone, $pin]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) return ["status"=>"error","message"=>"Invalid phone or PIN"];
        if ($user['credit_balance'] < $amount) return ["status"=>"error","message"=>"Insufficient credit balance"];

        $newCredit = $user['credit_balance'] - $amount;
        $newBalance = $user['balance'] + $amount;

        $stmt = $this->db->prepare("UPDATE wallets SET credit_balance=?, balance=? WHERE user_id=?");
        $stmt->execute([$newCredit, $newBalance, $user['id']]);

        $this->smsController->sendSms(0, $phone, "You transferred P$amount via USSD");

        return ["status"=>"success","message"=>"USSD transfer completed","balance"=>$newBalance,"credit_balance"=>$newCredit];
    }
}
