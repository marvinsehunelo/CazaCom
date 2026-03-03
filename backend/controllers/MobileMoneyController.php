<?php
require_once __DIR__ . '/../services/MobileMoneyService.php';
require_once __DIR__ . '/../services/ledgers/LedgerService.php';
require_once __DIR__ . '/../services/ledgers/WalletBalanceService.php';

class MobileMoneyController {
    private $mobileMoneyService;
    private $ledgerService;
    private $walletBalanceService;
    
    public function __construct($db) {
        $this->mobileMoneyService = new MobileMoneyService($db);
        $this->ledgerService = new LedgerService($db);
        $this->walletBalanceService = new WalletBalanceService($db);
    }
    
    /**
     * GET /mobile-money/balance - Get user's mobile money balance
     */
    public function getBalance($user_id) {
        try {
            $query = "SELECT balance, credit_balance, last_updated 
                      FROM mobile_money_accounts 
                      WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['user_id' => $user_id]);
            $balance = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$balance) {
                // Create mobile money account if doesn't exist
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
    
    /**
     * POST /mobile-money/deposit - Deposit to mobile money
     */
    public function deposit($user_id, $data) {
        try {
            $amount = floatval($data['amount']);
            if ($amount <= 0) {
                throw new Exception('Invalid amount');
            }
            
            $this->db->beginTransaction();
            
            // 1. Check main balance has sufficient funds
            $wallet = $this->walletBalanceService->getWallet($user_id);
            if ($wallet['balance'] < $amount) {
                throw new Exception('Insufficient main balance');
            }
            
            // 2. Debit main balance
            $this->walletBalanceService->debitMainBalance($user_id, $amount);
            
            // 3. Credit mobile money account
            $query = "UPDATE mobile_money_accounts 
                      SET balance = balance + :amount, 
                          last_updated = NOW() 
                      WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['amount' => $amount, 'user_id' => $user_id]);
            
            // 4. Record transaction
            $reference = 'MM-DEP-' . time() . '-' . $user_id;
            $this->recordMobileMoneyTransaction(
                $user_id, 
                'deposit', 
                $amount, 
                $reference, 
                'completed'
            );
            
            // 5. Create ledger entries
            $this->ledgerService->doubleEntry(
                $this->getMainBalanceLedger($user_id), // Debit
                $this->getMobileMoneyLedger($user_id),  // Credit
                $amount,
                'MOBILE_MONEY_DEPOSIT',
                null,
                "Deposit to mobile money: BWP{$amount}"
            );
            
            $this->db->commit();
            
            return [
                'status' => 'success',
                'message' => "Successfully deposited BWP{$amount} to mobile money",
                'reference' => $reference
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * POST /mobile-money/withdraw - Withdraw from mobile money
     */
    public function withdraw($user_id, $data) {
        try {
            $amount = floatval($data['amount']);
            if ($amount <= 0) {
                throw new Exception('Invalid amount');
            }
            
            $this->db->beginTransaction();
            
            // 1. Check mobile money balance
            $query = "SELECT balance FROM mobile_money_accounts WHERE user_id = :user_id FOR UPDATE";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['user_id' => $user_id]);
            $mmAccount = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$mmAccount || $mmAccount['balance'] < $amount) {
                throw new Exception('Insufficient mobile money balance');
            }
            
            // 2. Debit mobile money
            $query = "UPDATE mobile_money_accounts 
                      SET balance = balance - :amount, 
                          last_updated = NOW() 
                      WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['amount' => $amount, 'user_id' => $user_id]);
            
            // 3. Credit main balance
            $this->walletBalanceService->creditMainBalance($user_id, $amount);
            
            // 4. Record transaction
            $reference = 'MM-WTH-' . time() . '-' . $user_id;
            $this->recordMobileMoneyTransaction(
                $user_id, 
                'withdraw', 
                $amount, 
                $reference, 
                'completed'
            );
            
            $this->db->commit();
            
            return [
                'status' => 'success',
                'message' => "Successfully withdrew BWP{$amount} from mobile money",
                'reference' => $reference
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * POST /mobile-money/transfer - Send money to another mobile number
     */
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
            
            // Check balance
            $query = "SELECT balance FROM mobile_money_accounts WHERE user_id = :user_id FOR UPDATE";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['user_id' => $user_id]);
            $mmAccount = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$mmAccount || $mmAccount['balance'] < $total) {
                throw new Exception('Insufficient mobile money balance');
            }
            
            // Debit sender
            $query = "UPDATE mobile_money_accounts 
                      SET balance = balance - :total 
                      WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['total' => $total, 'user_id' => $user_id]);
            
            // Credit recipient if they exist in system
            $recipientUser = $this->getUserByPhone($recipient);
            if ($recipientUser) {
                $query = "UPDATE mobile_money_accounts 
                          SET balance = balance + :amount 
                          WHERE user_id = :user_id";
                $stmt = $this->db->prepare($query);
                $stmt->execute(['amount' => $amount, 'user_id' => $recipientUser['id']]);
            }
            
            // Record transaction
            $reference = 'MM-TRF-' . time() . '-' . $user_id;
            $this->recordMobileMoneyTransaction(
                $user_id, 
                'transfer', 
                $amount, 
                $reference, 
                'completed',
                $recipient,
                null,
                $fee
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
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * POST /mobile-money/airtime - Buy airtime
     */
    public function buyAirtime($user_id, $data) {
        try {
            $amount = floatval($data['amount']);
            $phone_number = $data['phone_number'] ?? $data['recipient'];
            $network = $data['network'];
            
            if ($amount <= 0) {
                throw new Exception('Invalid amount');
            }
            
            $this->db->beginTransaction();
            
            // Check balance
            $query = "SELECT balance FROM mobile_money_accounts WHERE user_id = :user_id FOR UPDATE";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['user_id' => $user_id]);
            $mmAccount = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$mmAccount || $mmAccount['balance'] < $amount) {
                throw new Exception('Insufficient mobile money balance');
            }
            
            // Debit mobile money
            $query = "UPDATE mobile_money_accounts 
                      SET balance = balance - :amount 
                      WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['amount' => $amount, 'user_id' => $user_id]);
            
            // Record transaction
            $reference = 'MM-AIR-' . time() . '-' . $user_id;
            $this->recordMobileMoneyTransaction(
                $user_id, 
                'airtime', 
                $amount, 
                $reference, 
                'completed',
                $phone_number,
                $network
            );
            
            // TODO: Call telco API to actually purchase airtime
            
            $this->db->commit();
            
            return [
                'status' => 'success',
                'message' => "Successfully purchased BWP{$amount} airtime for {$phone_number}",
                'reference' => $reference
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * POST /mobile-money/cross-wallet - Transfer between wallets
     */
    public function crossWalletTransfer($user_id, $data) {
        try {
            $amount = floatval($data['amount']);
            $source = $data['source_wallet']; // saccus, main, credit, mobile_money
            $destination = $data['destination_wallet'];
            
            if ($amount <= 0) {
                throw new Exception('Invalid amount');
            }
            
            $this->db->beginTransaction();
            
            // Handle different source/destination combinations
            $reference = 'CROSS-' . time() . '-' . $user_id;
            
            if ($source === 'saccus' && $destination === 'mobile_money') {
                // Debit Saccus eWallet
                $query = "UPDATE wallets 
                          SET saccus_ewallet_balance = saccus_ewallet_balance - :amount 
                          WHERE user_id = :user_id";
                $stmt = $this->db->prepare($query);
                $stmt->execute(['amount' => $amount, 'user_id' => $user_id]);
                
                // Credit Mobile Money
                $query = "UPDATE mobile_money_accounts 
                          SET balance = balance + :amount 
                          WHERE user_id = :user_id";
                $stmt = $this->db->prepare($query);
                $stmt->execute(['amount' => $amount, 'user_id' => $user_id]);
                
            } elseif ($source === 'mobile_money' && $destination === 'saccus') {
                // Debit Mobile Money
                $query = "UPDATE mobile_money_accounts 
                          SET balance = balance - :amount 
                          WHERE user_id = :user_id";
                $stmt = $this->db->prepare($query);
                $stmt->execute(['amount' => $amount, 'user_id' => $user_id]);
                
                // Credit Saccus eWallet
                $query = "UPDATE wallets 
                          SET saccus_ewallet_balance = saccus_ewallet_balance + :amount 
                          WHERE user_id = :user_id";
                $stmt = $this->db->prepare($query);
                $stmt->execute(['amount' => $amount, 'user_id' => $user_id]);
                
            } elseif ($source === 'main' && $destination === 'mobile_money') {
                // Debit Main Balance
                $query = "UPDATE wallets 
                          SET balance = balance - :amount 
                          WHERE user_id = :user_id AND balance >= :amount";
                $stmt = $this->db->prepare($query);
                $stmt->execute(['amount' => $amount, 'user_id' => $user_id]);
                
                if ($stmt->rowCount() === 0) {
                    throw new Exception('Insufficient main balance');
                }
                
                // Credit Mobile Money
                $query = "UPDATE mobile_money_accounts 
                          SET balance = balance + :amount 
                          WHERE user_id = :user_id";
                $stmt = $this->db->prepare($query);
                $stmt->execute(['amount' => $amount, 'user_id' => $user_id]);
            }
            
            // Record cross-wallet transaction
            $query = "INSERT INTO cross_wallet_transfers 
                      (user_id, source_wallet, destination_wallet, amount, reference, status, completed_at) 
                      VALUES 
                      (:user_id, :source, :destination, :amount, :reference, 'completed', NOW())";
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
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * GET /mobile-money/history - Get transaction history
     */
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
            
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'status' => 'success',
                'history' => $transactions
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    // ============ PRIVATE HELPER METHODS ============
    
    private function recordMobileMoneyTransaction($user_id, $type, $amount, $reference, $status, 
                                                  $recipient = null, $network = null, $fee = 0) {
        $query = "INSERT INTO mobile_money_transactions 
                  (user_id, type, amount, fee, reference, recipient_phone, network, status, completed_at) 
                  VALUES 
                  (:user_id, :type, :amount, :fee, :reference, :recipient, :network, :status, NOW())";
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
            return [
                'status' => 'error',
                'message' => 'Failed to create mobile money account: ' . $e->getMessage()
            ];
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
    
    private function getMainBalanceLedger($user_id) {
        // Get ledger account ID for main balance
        $query = "SELECT ledger_account_id FROM wallet_accounts WHERE user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['user_id' => $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['ledger_account_id'] : 1; // Default to asset account
    }
    
    private function getMobileMoneyLedger($user_id) {
        // Get ledger account ID for mobile money
        $query = "SELECT ledger_account_id FROM mobile_money_account_ledgers WHERE user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['user_id' => $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['ledger_account_id'] : 2; // Default to liability account
    }
}
