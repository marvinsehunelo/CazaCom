<?php
// services/SettlementService.php

class SettlementService {
    private $db;
    private $saccussalisApiUrl;
    private $apiKey;
    private $apiToken;
    
    public function __construct($db) {
        $this->db = $db;
        $this->saccussalisApiUrl = getenv('SACCUSSALIS_API_URL') ?: 'https://saccussalis.com';
        $this->apiKey = getenv('SACCUSSALIS_API_KEY') ?: 'SACCUS_INTERNAL_KEY_2025';
        $this->apiToken = getenv('SACCUSSALIS_API_TOKEN') ?: $this->getApiTokenFromDb();
    }
    
    private function getApiTokenFromDb() {
        try {
            $stmt = $this->db->prepare("SELECT token FROM api_tokens WHERE client_name = 'cazacom' AND active = true LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['token'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    public function recordOutgoingSettlement($user_id, $amount, $destinationBankAccount = null) {
        $reference = 'OUT-' . time() . '-' . $user_id . '-' . bin2hex(random_bytes(4));
        
        try {
            $response = $this->callSaccussalisApi('settlement/debit', [
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
            $response = $this->callSaccussalisApi('settlement/credit', [
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
    
    public function getTrustAccountBalance() {
        try {
            $response = $this->callSaccussalisApi('accounts/trust/balance', [], 'GET');
            return $response['balance'] ?? 0;
        } catch (Exception $e) {
            error_log("Failed to get trust balance: " . $e->getMessage());
            return 0;
        }
    }
    
    public function getDailyReconciliation() {
        try {
            $response = $this->callSaccussalisApi('reconciliation/daily', [], 'POST', true);
            return $response;
        } catch (Exception $e) {
            error_log("Failed to get daily reconciliation: " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
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
    
    private function callSaccussalisApi($endpoint, $data, $method = 'POST', $isInternal = false) {
        // Build URL with path parameter
        $url = rtrim($this->saccussalisApiUrl, '/') . '/backend/api.php?path=' . $endpoint;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        // Add authentication based on type
        if ($isInternal) {
            $headers[] = 'X-Internal-Key: ' . $this->apiKey;
        } else {
            // Use Bearer token for regular API calls
            if ($this->apiToken) {
                $headers[] = 'Authorization: Bearer ' . $this->apiToken;
            }
        }
        
        $headers[] = 'X-Source-System: CAZACOM';
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'GET' && !empty($data)) {
            $url .= '&' . http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $url);
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception("CURL error: {$curlError}");
        }
        
        if ($httpCode !== 200) {
            throw new Exception("API call failed with HTTP {$httpCode}: {$response}");
        }
        
        $decoded = json_decode($response, true);
        if (!$decoded) {
            throw new Exception("Invalid JSON response: {$response}");
        }
        
        return $decoded;
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
    
    public function getSettlementStatus($reference) {
        $stmt = $this->db->prepare("
            SELECT * FROM settlement_transactions 
            WHERE reference = :ref 
            LIMIT 1
        ");
        $stmt->execute(['ref' => $reference]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getTrustBalanceFromApi() {
        return $this->getTrustAccountBalance();
    }
}
