<?php
// services/BankBridgeService.php

require_once __DIR__ . '/../config/env.php'; // holds API keys, bank endpoints
require_once __DIR__ . '/../models/BankBridge.php';

/**
 * Handles the business logic for all bank bridging operations.
 * It coordinates between external APIs and the internal BankBridge model.
 */
class BankBridgeService {
    private $db;
    private $config;
    private $bridgeModel;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->config = include __DIR__ . '/../config/env.php';
        $this->bridgeModel = new BankBridge($db);
    }

    /**
     * Cash out from a bank eWallet to a user bank account.
     * @param string $bank The bank name.
     * @param int $userId The ID of the user.
     * @param float $amount The amount to cash out.
     * @param string $targetAccount The target bank account number.
     * @return array The API response.
     */
    public function cashOut(string $bank, int $userId, float $amount, string $targetAccount): array {
        // Simulated external API call (replace with curl to actual bank API)
        // Note: The original code had hardcoded responses.
        // In a real application, you would make an HTTP request here.
        $response = [
            "status" => "success",
            "message" => "Cashout of {$amount} from {$bank} successful.",
            "transaction_id" => uniqid("CO_")
        ];

        // Use the model to log the transaction
        $this->bridgeModel->logTransaction(
            $userId,
            "cashout",
            $amount,
            "completed",
            "Cashout to {$targetAccount} ({$bank})"
        );

        return $response;
    }

    /**
     * Create a new eWallet in the target bank.
     * @param string $bank The bank name.
     * @param array $userDetails The user's details.
     * @param float $initialBalance The initial balance for the new wallet.
     * @return array The API response.
     */
    public function createEwallet(string $bank, array $userDetails, float $initialBalance = 0): array {
        // Simulated external API call (replace with curl to actual bank API)
        // In a real application, you would make an HTTP request here.
        $accountId = uniqid("EWAL_");

        // Use the model to log the transaction
        $this->bridgeModel->logTransaction(
            $userDetails['id'],
            "create-ewallet",
            $initialBalance,
            "completed",
            "Created eWallet in {$bank} with ID {$accountId}"
        );

        return [
            "status" => "success",
            "message" => "eWallet created in {$bank}",
            "account_id" => $accountId,
            "balance" => $initialBalance
        ];
    }

    /**
     * Auto-bridge: a combination of a cash-out from one bank and a new eWallet creation in another.
     * @param string $fromBank The source bank name.
     * @param string $toBank The destination bank name.
     * @param int $userId The ID of the user.
     * @param float $amount The amount to bridge.
     * @param string $targetAccount The target bank account number.
     * @param array $userDetails The user's details.
     * @return array The combined API response.
     */
    public function autoBridge(string $fromBank, string $toBank, int $userId, float $amount, string $targetAccount, array $userDetails): array {
        $cashout = $this->cashOut($fromBank, $userId, $amount, $targetAccount);

        if ($cashout['status'] !== 'success') {
            return [
                "status" => "error",
                "message" => "Cashout failed from {$fromBank}"
            ];
        }

        $ewallet = $this->createEwallet($toBank, $userDetails, $amount);

        // Use the model to log the main bridge transaction
        $this->bridgeModel->logTransaction(
            $userId,
            "autobridge",
            $amount,
            "completed",
            "Bridged from {$fromBank} to {$toBank}"
        );

        return [
            "status" => "success",
            "message" => "Successfully bridged funds from {$fromBank} to {$toBank}.",
            "cashout" => $cashout,
            "ewallet" => $ewallet
        ];
    }
}
