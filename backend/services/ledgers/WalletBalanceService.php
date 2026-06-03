<?php
// services/ledgers/WalletBalanceService.php

class WalletBalanceService {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getBalance($accountId) {
        $stmt = $this->db->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN credit_account = ? THEN amount END), 0)
              - COALESCE(SUM(CASE WHEN debit_account = ? THEN amount END), 0)
            AS balance
            FROM ledger_entries
        ");
        $stmt->execute([$accountId, $accountId]);
        return (float)$stmt->fetch()['balance'];
    }

    public function getWallet($user_id) {
        $stmt = $this->db->prepare("
            SELECT balance, credit_balance, saccus_ewallet_balance 
            FROM wallets 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function debitMainBalance($user_id, $amount) {
        $stmt = $this->db->prepare("
            UPDATE wallets 
            SET balance = balance - ?, last_updated = NOW() 
            WHERE user_id = ? AND balance >= ?
        ");
        $stmt->execute([$amount, $user_id, $amount]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Insufficient main balance");
        }
        return true;
    }

    public function creditMainBalance($user_id, $amount) {
        $stmt = $this->db->prepare("
            UPDATE wallets 
            SET balance = balance + ?, last_updated = NOW() 
            WHERE user_id = ?
        ");
        $stmt->execute([$amount, $user_id]);
        return true;
    }

    public function getSaccusEwalletBalance($user_id) {
        $stmt = $this->db->prepare("
            SELECT saccus_ewallet_balance 
            FROM wallets 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? floatval($result['saccus_ewallet_balance']) : 0;
    }

    public function debitSaccusEwallet($user_id, $amount) {
        $stmt = $this->db->prepare("
            UPDATE wallets 
            SET saccus_ewallet_balance = saccus_ewallet_balance - ?, last_updated = NOW() 
            WHERE user_id = ? AND saccus_ewallet_balance >= ?
        ");
        $stmt->execute([$amount, $user_id, $amount]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Insufficient Saccus eWallet balance");
        }
        return true;
    }

    public function creditSaccusEwallet($user_id, $amount) {
        $stmt = $this->db->prepare("
            UPDATE wallets 
            SET saccus_ewallet_balance = saccus_ewallet_balance + ?, last_updated = NOW() 
            WHERE user_id = ?
        ");
        $stmt->execute([$amount, $user_id]);
        return true;
    }
}
