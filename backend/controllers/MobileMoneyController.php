<?php
require_once __DIR__ . '/../services/MobileMoneyService.php';
require_once __DIR__ . '/../services/ledgers/LedgerService.php';
require_once __DIR__ . '/../services/ledgers/WalletBalanceService.php';
require_once __DIR__ . '/../services/SettlementService.php';

class MobileMoneyController {
    private $db;
    private $mobileMoneyService;
    private $ledgerService;
    private $walletBalanceService;
    private $settlementService;
    
    public function __construct($db) {
        $this->db = $db;
        $this->mobileMoneyService = new MobileMoneyService($db);
        $this->ledgerService = new LedgerService($db);
        $this->walletBalanceService = new WalletBalanceService($db);
        $this->settlementService = new SettlementService($db);
    }
    
    public function getBalance($user_id) {
        try {
            $query = "SELECT balance, credit_balance, last_updated 
                      FROM mobile_money_accounts 
                      WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['user_id' => $user_id]);
            $balance = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$balance) {
                return $this->createMobileMoneyAccount($user_id);
            }
            
            return [
                'status' => 'success',
                'mobile_money_balance' => floatval($balance['balance']),
                'credit_balance' => floatval($balance['credit_balance']),
                'last_updated' => $balance['last_updated']
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Failed to fetch mobile money balance: ' . $e->getMessage()
            ];
        }
    }
    
    public function deposit($user_id, $data) {
        try {
            $amount = floatval($data['amount']);
            $source = $data['source'] ?? 'main';
            
            if ($amount <= 0) {
                throw new Exception('Invalid amount');
            }
            
            $this->db->beginTransaction();
            
            if ($source === 'external_bank') {
                // Money coming from external bank - NO internal debit
                // Settlement service will handle trust account credit
                $reference = $this->settlementService->recordIncomingSettlement($user_id, $amount, $data['bank_ref'] ?? null);
            } else {
                // Internal transfer from main balance
                $wallet = $this->walletBalanceService->getWallet($user_id);
                if (!$wallet || $wallet['balance'] < $amount) {
                    throw new Exception('Insufficient main balance');
                }
                
                $this->walletBalanceService->debitMainBalance($user_id, $amount);
                $reference = 'MM-DEP-' . time() . '-' . $user_id;
            }
            
            $query = "UPDATE mobile_money_accounts 
                      SET balance = balance + :amount, last_updated = NOW() 
                      WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['amount' => $amount, 'user_id' => $user_id]);
            
            $this->recordMobileMoneyTransaction(
                $user_id, 'deposit', $amount, $reference, 'completed'
            );
            
            $this->db->commit();
            
            return [
                'status' => 'success',
                'message' => "Successfully deposited BWP{$amount} to mobile money",
                'reference' => $reference
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    public function withdraw($user_id, $data) {
        try {
            $amount = floatval($data['amount']);
            $destination = $data['destination'] ?? 'main';
            
            if ($amount <= 0) {
                throw new Exception('Invalid amount');
            }
            
            $this->db->beginTransaction();
            
            $query = "SELECT balance FROM mobile_money_accounts WHERE user_id = :user_id FOR UPDATE";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['user_id' => $user_id]);
            $mmAccount = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$mmAccount || $mmAccount['balance'] < $amount) {
                throw new Exception('Insufficient mobile money balance');
            }
            
            $query = "UPDATE mobile_money_accounts 
                      SET balance = balance - :amount, last_updated = NOW() 
                      WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['amount' => $amount, 'user_id' => $user_id]);
            
            if ($destination === 'external_bank') {
                $reference = $this->settlementService->recordOutgoingSettlement($user_id, $amount, $data['bank_account'] ?? null);
            } else {
                $this->walletBalanceService->creditMainBalance($user_id, $amount);
                $reference = 'MM-WTH-' . time() . '-' . $user_id;
            }
            
            $this->recordMobileMoneyTransaction(
                $user_id, 'withdraw', $amount, $reference, 'completed'
            );
            
            $this->db->commit();
            
            return [
                'status' => 'success',
                'message' => "Successfully withdrew BWP{$amount} from mobile money",
                'reference' => $reference
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    public function transfer($user_id, $data) {
        try {
            $amount = floatval($data['amount']);
            $recipient = $data['recipient'];
            
            if ($amount <= 0) {
                throw new Exception('Invalid amount');
            }
            if (empty($recipient)) {
                throw new Exception('Recipient phone number required');
            }
            
            $fee = $this->calculateTransferFee($amount);
            $total = $amount + $fee;
            
            $this->db->beginTransaction();
            
            $query = "SELECT balance FROM mobile_money_accounts WHERE user_id = :user_id FOR UPDATE";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['user_id' => $user_id]);
            $mmAccount = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$mmAccount || $mmAccount['balance'] < $total) {
                throw new Exception('Insufficient mobile money balance');
            }
            
            $query = "UPDATE mobile_money_accounts 
                      SET balance = balance - :total 
                      WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['total' => $total, 'user_id' => $user_id]);
            
            $recipientUser = $this->getUserByPhone($recipient);
            if ($recipientUser) {
                $query = "UPDATE mobile_money_accounts 
                          SET balance = balance + :amount 
                          WHERE user_id = :user_id";
                $stmt = $this->db->prepare($query);
                $stmt->execute(['amount' => $amount, 'user_id' => $recipientUser['id']]);
            }
            
            $reference = 'MM-TRF-' . time() . '-' . $user_id;
            $this->recordMobileMoneyTransaction(
                $user_id, 'transfer', $amount, $reference, 'completed',
                $recipient, null, $fee
            );
            
            $this->db->commit();
            
            return [
                'status' => 'success',
                'message' => "Successfully sent BWP{$amount} to {$recipient}",
                'fee' => $fee,
                'reference' => $reference
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    public function p2pTransfer($user_id, $data) {
        try {
            $amount = floatval($data['amount']);
            $recipientId = intval($data['recipient_user_id']);
            
            if ($amount <= 0) {
                throw new Exception('Invalid amount');
            }
            
            $this->db->beginTransaction();
            
            $query = "SELECT balance FROM mobile_money_accounts WHERE user_id = :user_id FOR UPDATE";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['user_id' => $user_id]);
            $sender = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$sender || $sender['balance'] < $amount) {
                throw new Exception('Insufficient balance');
            }
            
            $query = "UPDATE mobile_money_accounts SET balance = balance - :amount WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['amount' => $amount, 'user_id' => $user_id]);
            
            $query = "UPDATE mobile_money_accounts SET balance = balance + :amount WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['amount' => $amount, 'user_id' => $recipientId]);
            
            $reference = 'P2P-' . time() . '-' . $user_id;
            $this->recordMobileMoneyTransaction($user_id, 'p2p', $amount, $reference, 'completed', null, null, 0);
            $this->recordMobileMoneyTransaction($recipientId, 'p2p_receive', $amount, $reference, 'completed', null, null, 0);
            
            $this->db->commit();
            
            return [
                'status' => 'success',
                'message' => "Successfully sent BWP{$amount} to user {$recipientId}",
                'reference' => $reference
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    public function crossWalletTransfer($user_id, $data) {
        try {
            $amount = floatval($data['amount']);
            $source = $data['source_wallet'];
            $destination = $data['destination_wallet'];
            
            if ($amount <= 0) {
                throw new Exception('Invalid amount');
            }
            
            $this->db->beginTransaction();
            $reference = 'CROSS-' . time() . '-' . $user_id;
            
            if ($source === 'saccus' && $destination === 'mobile_money') {
                $query = "UPDATE wallets SET saccus_ewallet_balance = saccus_ewallet_balance - :amount WHERE user_id = :user_id";
                $stmt = $this->db->prepare($query);
                $stmt->execute(['amount' => $amount, 'user_id' => $user_id]);
                
                $query = "UPDATE mobile_money_accounts SET balance = balance + :amount WHERE user_id = :user_id";
                $stmt = $this->db->prepare($query);
                $stmt->execute(['amount' => $amount, 'user_id' => $user_id]);
                
                $this->settlementService->recordInternalTransfer($user_id, 'saccus_to_mobile', $amount);
                
            } elseif ($source === 'mobile_money' && $destination === 'saccus') {
                $query = "SELECT balance FROM mobile_money_accounts WHERE user_id = :user_id FOR UPDATE";
                $stmt = $this->db->prepare($query);
                $stmt->execute(['user_id' => $user_id]);
                $mmAccount = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$mmAccount || $mmAccount['balance'] < $amount) {
                    throw new Exception('Insufficient mobile money balance');
                }
                
                $query = "UPDATE mobile_money_accounts SET balance = balance - :amount WHERE user_id = :user_id";
                $stmt = $this->db->prepare($query);
                $stmt->execute(['amount' => $amount, 'user_id' => $user_id]);
                
                $query = "UPDATE wallets SET saccus_ewallet_balance = saccus_ewallet_balance + :amount WHERE user_id = :user_id";
                $stmt = $this->db->prepare($query);
                $stmt->execute(['amount' => $amount, 'user_id' => $user_id]);
                
                $this->settlementService->recordInternalTransfer($user_id, 'mobile_to_saccus', $amount);
            }
            
            $query = "INSERT INTO cross_wallet_transfers 
                      (user_id, source_wallet, destination_wallet, amount, reference, status, completed_at) 
                      VALUES (:user_id, :source, :destination, :amount, :reference, 'completed', NOW())";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'user_id' => $user_id,
                'source' => $source,
                'destination' => $destination,
                'amount' => $amount,
                'reference' => $reference
            ]);
            
            $this->db->commit();
            
            return [
                'status' => 'success',
                'message' => "Successfully transferred BWP{$amount} from {$source} to {$destination}",
                'reference' => $reference
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    public function getHistory($user_id, $limit = 50) {
        try {
            $query = "SELECT * FROM mobile_money_transactions 
                      WHERE user_id = :user_id 
                      ORDER BY created_at DESC 
                      LIMIT :limit";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return [
                'status' => 'success',
                'history' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
            
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    private function recordMobileMoneyTransaction($user_id, $type, $amount, $reference, $status, $recipient = null, $network = null, $fee = 0) {
        $query = "INSERT INTO mobile_money_transactions 
                  (user_id, type, amount, fee, reference, recipient_phone, network, status, completed_at) 
                  VALUES (:user_id, :type, :amount, :fee, :reference, :recipient, :network, :status, NOW())";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'user_id' => $user_id,
            'type' => $type,
            'amount' => $amount,
            'fee' => $fee,
            'reference' => $reference,
            'recipient' => $recipient,
            'network' => $network,
            'status' => $status
        ]);
    }
    
    private function createMobileMoneyAccount($user_id) {
        try {
            $query = "INSERT INTO mobile_money_accounts (user_id, balance, credit_balance) 
                      VALUES (:user_id, 0.00, 0.00) 
                      RETURNING balance, credit_balance, last_updated";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['user_id' => $user_id]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'status' => 'success',
                'mobile_money_balance' => floatval($account['balance']),
                'credit_balance' => floatval($account['credit_balance']),
                'last_updated' => $account['last_updated']
            ];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Failed to create mobile money account: ' . $e->getMessage()];
        }
    }
    
    private function getUserByPhone($phone) {
        $query = "SELECT id, name, phone_number FROM users WHERE phone_number = :phone";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['phone' => $phone]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function calculateTransferFee($amount) {
        if ($amount <= 100) return 1.50;
        if ($amount <= 500) return 2.50;
        if ($amount <= 1000) return 3.50;
        return 5.00;
    }
}
