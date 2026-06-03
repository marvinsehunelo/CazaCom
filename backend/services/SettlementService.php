<?php
// services/SettlementService.php

class SettlementService {
    private $db;
    private $saccussalisApiUrl;
    private $apiKey;
    
    public function __construct($db) {
        $this->db = $db;
        $this->saccussalisApiUrl = getenv('SACCUSSALIS_API_URL') ?: 'https://saccussalis.com/api';
        $this->apiKey = getenv('SACCUSSALIS_API_KEY') ?: 'SACCUS_INTERNAL_KEY_2025';
    }
    
    public function recordOutgoingSettlement($user_id, $amount, $destinationBankAccount = null) {
        $reference = 'OUT-' . time() . '-' . $user_id . '-' . bin2hex(random_bytes(4));
        
        try {
            $response = $this->callSaccussalisApi('/settlement/debit', [
                'amount' => $amount,
                'currency' => 'BWP',
                'reference' => $reference,
                'destination_account' => $destinationBankAccount,
                'customer_phone' => $this->getUserPhone($user_id)
            ]);
            
            if ($response['status'] === 'success') {
                $stmt = $this->db->prepare("
                    INSERT INTO settlement_transactions 
                    (reference, user_id, amount, type, status, external_reference, created_at) 
                    VALUES (:ref, :user_id, :amount, 'outgoing', 'completed', :ext_ref, NOW())
                ");
                $stmt->execute([
                    'ref' => $reference,
                    'user_id' => $user_id,
                    'amount' => $amount,
                    'ext_ref' => $response['transaction_id'] ?? null
                ]);
                
                $this->updateTrustReconciliation($amount, 'decrease');
                return $reference;
            }
            
            throw new Exception($response['message'] ?? 'Settlement failed');
            
        } catch (Exception $e) {
            $stmt = $this->db->prepare("
                INSERT INTO settlement_transactions 
                (reference, user_id, amount, type, status, error_message, created_at) 
                VALUES (:ref, :user_id, :amount, 'outgoing', 'failed', :error, NOW())
            ");
            $stmt->execute([
                'ref' => $reference,
                'user_id' => $user_id,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    public function recordIncomingSettlement($user_id, $amount, $bankReference = null) {
        $reference = 'IN-' . time() . '-' . $user_id . '-' . bin2hex(random_bytes(4));
        
        try {
            $response = $this->callSaccussalisApi('/settlement/credit', [
                'amount' => $amount,
                'currency' => 'BWP',
                'reference' => $reference,
                'source_reference' => $bankReference,
                'customer_phone' => $this->getUserPhone($user_id)
            ]);
            
            if ($response['status'] === 'success') {
                $stmt = $this->db->prepare("
                    INSERT INTO settlement_transactions 
                    (reference, user_id, amount, type, status, external_reference, created_at) 
                    VALUES (:ref, :user_id, :amount, 'incoming', 'completed', :ext_ref, NOW())
                ");
                $stmt->execute([
                    'ref' => $reference,
                    'user_id' => $user_id,
                    'amount' => $amount,
                    'ext_ref' => $response['transaction_id'] ?? null
                ]);
                
                $this->updateTrustReconciliation($amount, 'increase');
                return $reference;
            }
            
            throw new Exception($response['message'] ?? 'Settlement failed');
            
        } catch (Exception $e) {
            $stmt = $this->db->prepare("
                INSERT INTO settlement_transactions 
                (reference, user_id, amount, type, status, error_message, created_at) 
                VALUES (:ref, :user_id, :amount, 'incoming', 'failed', :error, NOW())
            ");
            $stmt->execute([
                'ref' => $reference,
                'user_id' => $user_id,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    public function recordInternalTransfer($user_id, $transferType, $amount) {
        $reference = 'INT-' . time() . '-' . $user_id;
        
        $stmt = $this->db->prepare("
            INSERT INTO internal_transfers 
            (reference, user_id, transfer_type, amount, status, created_at) 
            VALUES (:ref, :user_id, :type, :amount, 'completed', NOW())
        ");
        $stmt->execute([
            'ref' => $reference,
            'user_id' => $user_id,
            'type' => $transferType,
            'amount' => $amount
        ]);
        
        return $reference;
    }
    
    public function reconcileTrustAccount() {
        try {
            $trustBalance = $this->getTrustAccountBalance();
            $totalLiabilities = $this->getTotalCustomerBalances();
            $variance = $trustBalance - $totalLiabilities;
            
            $stmt = $this->db->prepare("
                INSERT INTO trust_reconciliation 
                (bank_reported_balance, emoney_liability, variance, status, checked_at) 
                VALUES (:bank, :liability, :variance, :status, NOW())
            ");
            
            $status = abs($variance) < 0.01 ? 'MATCH' : 'BREACH';
            $stmt->execute([
                'bank' => $trustBalance,
                'liability' => $totalLiabilities,
                'variance' => $variance,
                'status' => $status
            ]);
            
            if ($status === 'BREACH') {
                $this->sendReconciliationAlert($trustBalance, $totalLiabilities, $variance);
            }
            
            return [
                'trust_balance' => $trustBalance,
                'total_liabilities' => $totalLiabilities,
                'variance' => $variance,
                'status' => $status
            ];
            
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    private function getTrustAccountBalance() {
        $response = $this->callSaccussalisApi('/accounts/trust/balance', []);
        return $response['balance'] ?? 0;
    }
    
    private function getTotalCustomerBalances() {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(balance), 0) as total 
            FROM mobile_money_accounts
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return floatval($result['total']);
    }
    
    private function updateTrustReconciliation($amount, $direction) {
        $adjustment = ($direction === 'increase') ? $amount : -$amount;
        
        $stmt = $this->db->prepare("
            UPDATE trust_reconciliation 
            SET variance = variance + :adjustment 
            WHERE id = (SELECT id FROM trust_reconciliation ORDER BY id DESC LIMIT 1)
        ");
        $stmt->execute(['adjustment' => $adjustment]);
    }
    
    private function callSaccussalisApi($endpoint, $data) {
        $ch = curl_init($this->saccussalisApiUrl . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Internal-Key: ' . $this->apiKey,
            'X-Source-System: CAZACOM'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("API call failed with HTTP {$httpCode}: {$response}");
        }
        
        return json_decode($response, true);
    }
    
    private function getUserPhone($user_id) {
        $stmt = $this->db->prepare("SELECT phone_number FROM users WHERE id = :id");
        $stmt->execute(['id' => $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['phone_number'] ?? null;
    }
    
    private function sendReconciliationAlert($trustBalance, $liabilities, $variance) {
        $stmt = $this->db->prepare("
            INSERT INTO alerts (type, severity, message, created_at) 
            VALUES ('RECONCILIATION_BREACH', 'HIGH', :message, NOW())
        ");
        $message = "Trust reconciliation breach: Trust={$trustBalance}, Liabilities={$liabilities}, Variance={$variance}";
        $stmt->execute(['message' => $message]);
        
        error_log($message);
    }
}
