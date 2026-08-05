<?php
declare(strict_types=1);

/**
 * MTN MoMo Participant — single-file simulation, hosted on Cazacom's
 * infrastructure. MTN remains an independent DIRECT switch
 * participant with its own settlement account and API key.
 *
 * transfer()/getTransferStatus() mirror MTN MoMo's real public
 * Disbursement API so switching $sandboxMode to call the real MTN
 * sandbox later is a drop-in change. Agent cashout is local business
 * logic layered on top (MTN's real API has no concept of it).
 *
 * Entry point routes on ?action=:
 *   POST ?action=switch_webhook   <- called BY centralswitch (signed)
 *   POST ?action=initiate_cashout <- called by MTN app/USSD
 *   POST ?action=confirm_cashout  <- called by agent app
 *   GET  ?action=wallet_balance
 */

require_once __DIR__ . '/../config/db.php'; // adjust path if MTN's DB connection differs from the switch's

header('Content-Type: application/json');

class MtnMomoParticipant
{
    private PDO $db;
    private bool $sandboxMode;

    private ?string $subscriptionKey;
    private ?string $apiUser;
    private ?string $apiKey;
    private ?string $baseUrl;
    private ?string $cachedToken = null;
    private ?int $tokenExpiresAt = null;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->sandboxMode = (getenv('MTN_MODE') ?: 'LOCAL') === 'LOCAL';

        $this->subscriptionKey = getenv('MTN_SUBSCRIPTION_KEY') ?: null;
        $this->apiUser = getenv('MTN_API_USER') ?: null;
        $this->apiKey = getenv('MTN_API_KEY') ?: null;
        $this->baseUrl = getenv('MTN_BASE_URL') ?: 'https://sandbox.momodeveloper.mtn.com';
    }

    // ============================================================
    // ENTRY POINT
    // ============================================================
    public function handleRequest(string $action, array $input, string $rawBody, array $headers): array
    {
        return match ($action) {
            'switch_webhook' => $this->handleSwitchWebhook($input, $rawBody, $headers),
            'initiate_cashout' => $this->initiateAgentCashout($input),
            'confirm_cashout' => $this->confirmAgentCashout($input),
            'wallet_balance' => $this->getWalletBalance($input['msisdn'] ?? ''),
            default => ['success' => false, 'message' => "Unknown action: {$action}"],
        };
    }

    // ============================================================
    // 1. SWITCH WEBHOOK — verifies the request actually came from
    //    centralswitch before doing anything, then fans the switch's
    //    aggregate settlement out to the specific customer wallet.
    // ============================================================
    private function handleSwitchWebhook(array $input, string $rawBody, array $headers): array
    {
        $headersLower = array_change_key_case($headers, CASE_LOWER);
        $timestamp = $headersLower['x-api-timestamp'] ?? null;
        $signature = $headersLower['x-api-signature'] ?? null;
        $secret = getenv('MTN_SWITCH_SECRET') ?: null;

        if (!$timestamp || !$signature) {
            http_response_code(401);
            return ['success' => false, 'message' => 'Missing signature headers'];
        }
        if (!$secret) {
            http_response_code(500);
            error_log("[MTN MoMo] MTN_SWITCH_SECRET not configured — cannot verify webhook");
            return ['success' => false, 'message' => 'Server not configured to verify webhooks'];
        }
        if (abs(time() - (int)$timestamp) > 300) {
            http_response_code(401);
            return ['success' => false, 'message' => 'Signature timestamp expired'];
        }

        // IMPORTANT: signs the RAW body, exactly as the switch signed it.
        // Re-encoding $input via json_encode() here would produce a
        // different byte string (key order, whitespace) and always fail.
        $expected = hash_hmac('sha256', $timestamp . $rawBody, $secret);
        if (!hash_equals($expected, $signature)) {
            http_response_code(401);
            error_log("[MTN MoMo] Webhook signature verification FAILED");
            return ['success' => false, 'message' => 'Invalid signature'];
        }

        // ---- Verified. Proceed. ----
        $required = ['transaction_reference', 'status'];
        foreach ($required as $r) {
            if (empty($input[$r])) {
                return ['success' => false, 'message' => "Missing field: {$r}"];
            }
        }

        // NOTE: dispatchCallback() on the switch side currently sends
        // only {transaction_reference, status, timestamp} — it does NOT
        // include amount/currency/destination_account_number. If you
        // want handleSwitchWebhook to actually credit a wallet here
        // (rather than just acknowledging), extend dispatchCallback()
        // in submit_transfer.php to include those three fields in the
        // signed payload, or have MTN look them up via GET
        // api/v1/transactions.php?reference=... using its own API key
        // once it receives this notification. Flagging this gap
        // explicitly rather than silently guessing values.
        if ($input['status'] !== 'COMPLETED') {
            return ['success' => true, 'message' => 'Acknowledged, no wallet action for non-COMPLETED status'];
        }

        if (empty($input['amount']) || empty($input['currency']) || empty($input['destination_account_number'])) {
            error_log("[MTN MoMo] Webhook verified but missing amount/currency/destination_account_number for {$input['transaction_reference']} — cannot credit wallet yet. Extend dispatchCallback() payload.");
            return ['success' => true, 'message' => 'Signature verified, but payload incomplete for wallet credit — see server log'];
        }

        $referenceId = 'MTNXFER_' . hash('sha256', $input['transaction_reference']);

        return $this->transfer([
            'reference_id' => $referenceId,
            'switch_transaction_reference' => $input['transaction_reference'],
            'payee_msisdn' => $input['destination_account_number'],
            'amount' => (float)$input['amount'],
            'currency' => $input['currency'],
            'payer_message' => 'Switch settlement',
            'payee_note' => "Credit for {$input['transaction_reference']}",
        ]);
    }

    // ============================================================
    // 2. transfer() — mirrors MTN MoMo Disbursement /transfer.
    //    Idempotent on reference_id.
    // ============================================================
    public function transfer(array $params): array
    {
        $stmt = $this->db->prepare("SELECT * FROM mtn_transfers WHERE reference_id = ?");
        $stmt->execute([$params['reference_id']]);
        $existing = $stmt->fetch();
        if ($existing) {
            return ['success' => true, 'message' => 'Duplicate reference_id — returning existing transfer', 'data' => $existing];
        }

        return $this->sandboxMode ? $this->localTransfer($params) : $this->remoteTransfer($params);
    }

    private function localTransfer(array $params): array
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT wallet_id, balance FROM mtn_wallets WHERE msisdn = ? FOR UPDATE");
            $stmt->execute([$params['payee_msisdn']]);
            $wallet = $stmt->fetch();

            if (!$wallet) {
                $stmt = $this->db->prepare("
                    INSERT INTO mtn_wallets (msisdn, wallet_type, balance)
                    VALUES (?, 'CUSTOMER', 0) RETURNING wallet_id, balance
                ");
                $stmt->execute([$params['payee_msisdn']]);
                $wallet = $stmt->fetch();
            }

            $this->db->prepare("UPDATE mtn_wallets SET balance = balance + ? WHERE wallet_id = ?")
                ->execute([$params['amount'], $wallet['wallet_id']]);

            $stmt = $this->db->prepare("
                INSERT INTO mtn_transfers (
                    reference_id, switch_transaction_reference, payee_msisdn,
                    amount, currency, status, payer_message, payee_note, completed_at
                ) VALUES (?, ?, ?, ?, ?, 'SUCCESSFUL', ?, ?, NOW())
                RETURNING *
            ");
            $stmt->execute([
                $params['reference_id'], $params['switch_transaction_reference'] ?? null,
                $params['payee_msisdn'], $params['amount'], $params['currency'],
                $params['payer_message'] ?? '', $params['payee_note'] ?? '',
            ]);
            $transfer = $stmt->fetch();

            $this->db->commit();
            return ['success' => true, 'message' => 'Transfer successful (simulated)', 'data' => $transfer];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Transfer failed: ' . $e->getMessage()];
        }
    }

    private function remoteTransfer(array $params): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'message' => 'Could not obtain MTN access token'];
        }

        $body = json_encode([
            'amount' => (string)$params['amount'],
            'currency' => $params['currency'],
            'externalId' => $params['reference_id'],
            'payee' => ['partyIdType' => 'MSISDN', 'partyId' => $params['payee_msisdn']],
            'payerMessage' => $params['payer_message'] ?? '',
            'payeeNote' => $params['payee_note'] ?? '',
        ]);

        $ch = curl_init($this->baseUrl . '/disbursement/v1_0/transfer');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'X-Reference-Id: ' . $params['reference_id'],
                'X-Target-Environment: sandbox',
                'Ocp-Apim-Subscription-Key: ' . $this->subscriptionKey,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $status = ($httpCode === 202) ? 'PENDING' : 'FAILED';

        $stmt = $this->db->prepare("
            INSERT INTO mtn_transfers (
                reference_id, switch_transaction_reference, payee_msisdn,
                amount, currency, status, payer_message, payee_note
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING *
        ");
        $stmt->execute([
            $params['reference_id'], $params['switch_transaction_reference'] ?? null,
            $params['payee_msisdn'], $params['amount'], $params['currency'],
            $status, $params['payer_message'] ?? '', $params['payee_note'] ?? '',
        ]);

        return ['success' => $httpCode === 202, 'message' => "MTN responded HTTP {$httpCode}", 'data' => $stmt->fetch()];
    }

    public function getTransferStatus(string $referenceId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM mtn_transfers WHERE reference_id = ?");
        $stmt->execute([$referenceId]);
        $transfer = $stmt->fetch();
        return $transfer ? ['success' => true, 'data' => $transfer] : ['success' => false, 'message' => 'Reference not found'];
    }

    private function getAccessToken(): ?string
    {
        if ($this->cachedToken && $this->tokenExpiresAt > time()) {
            return $this->cachedToken;
        }
        if (!$this->apiUser || !$this->apiKey || !$this->subscriptionKey) {
            return null;
        }

        $ch = curl_init($this->baseUrl . '/disbursement/token/');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_HTTPHEADER => [
                'Ocp-Apim-Subscription-Key: ' . $this->subscriptionKey,
                'Authorization: Basic ' . base64_encode($this->apiUser . ':' . $this->apiKey),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) return null;

        $this->cachedToken = $data['access_token'];
        $this->tokenExpiresAt = time() + (int)($data['expires_in'] ?? 3600) - 60;
        return $this->cachedToken;
    }

    // ============================================================
    // 3. AGENT CASHOUT — local business logic, not part of MTN's
    //    real public API.
    // ============================================================
    public function initiateAgentCashout(array $params): array
    {
        $required = ['customer_msisdn', 'agent_msisdn', 'amount', 'currency'];
        foreach ($required as $r) {
            if (empty($params[$r]) && $params[$r] !== 0) {
                return ['success' => false, 'message' => "Missing field: {$r}"];
            }
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT wallet_id, balance FROM mtn_wallets WHERE msisdn = ? FOR UPDATE");
            $stmt->execute([$params['customer_msisdn']]);
            $customerWallet = $stmt->fetch();

            if (!$customerWallet || $customerWallet['balance'] < $params['amount']) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Insufficient customer wallet balance'];
            }

            $stmt = $this->db->prepare("SELECT wallet_id FROM mtn_wallets WHERE msisdn = ? AND wallet_type = 'AGENT' FOR UPDATE");
            $stmt->execute([$params['agent_msisdn']]);
            $agentWallet = $stmt->fetch();
            if (!$agentWallet) {
                $stmt = $this->db->prepare("INSERT INTO mtn_wallets (msisdn, wallet_type, balance) VALUES (?, 'AGENT', 0) RETURNING wallet_id");
                $stmt->execute([$params['agent_msisdn']]);
                $agentWallet = $stmt->fetch();
            }

            $this->db->prepare("UPDATE mtn_wallets SET balance = balance - ? WHERE wallet_id = ?")
                ->execute([$params['amount'], $customerWallet['wallet_id']]);
            $this->db->prepare("UPDATE mtn_wallets SET balance = balance + ? WHERE wallet_id = ?")
                ->execute([$params['amount'], $agentWallet['wallet_id']]);

            $cashoutRef = 'CASHOUT' . date('YmdHis') . rand(1000, 9999);
            $cashCode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $stmt = $this->db->prepare("
                INSERT INTO mtn_agent_cashouts (
                    cashout_reference, customer_msisdn, agent_msisdn,
                    amount, currency, status, cash_code, expires_at
                ) VALUES (?, ?, ?, ?, ?, 'FUNDED', ?, NOW() + INTERVAL '30 minutes')
                RETURNING *
            ");
            $stmt->execute([
                $cashoutRef, $params['customer_msisdn'], $params['agent_msisdn'],
                $params['amount'], $params['currency'], $cashCode,
            ]);
            $cashout = $stmt->fetch();

            $this->db->commit();

            return ['success' => true, 'message' => 'Cashout funded — agent wallet credited, awaiting handoff', 'data' => $cashout];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Cashout initiation failed: ' . $e->getMessage()];
        }
    }

    public function confirmAgentCashout(array $params): array
    {
        if (empty($params['cashout_reference']) || empty($params['cash_code'])) {
            return ['success' => false, 'message' => 'cashout_reference and cash_code required'];
        }

        $stmt = $this->db->prepare("SELECT * FROM mtn_agent_cashouts WHERE cashout_reference = ? FOR UPDATE");
        $stmt->execute([$params['cashout_reference']]);
        $cashout = $stmt->fetch();

        if (!$cashout) return ['success' => false, 'message' => 'Cashout not found'];
        if ($cashout['status'] !== 'FUNDED') return ['success' => false, 'message' => "Cashout is not awaiting handoff (status: {$cashout['status']})"];
        if (strtotime($cashout['expires_at']) < time()) {
            $this->db->prepare("UPDATE mtn_agent_cashouts SET status = 'EXPIRED' WHERE cashout_id = ?")->execute([$cashout['cashout_id']]);
            return ['success' => false, 'message' => 'Cashout code expired'];
        }
        if (!hash_equals($cashout['cash_code'], $params['cash_code'])) {
            return ['success' => false, 'message' => 'Incorrect cash code'];
        }

        $this->db->prepare("UPDATE mtn_agent_cashouts SET status = 'COMPLETED', completed_at = NOW() WHERE cashout_id = ?")
            ->execute([$cashout['cashout_id']]);

        return ['success' => true, 'message' => 'Cash handed over — cashout completed'];
    }

    public function getWalletBalance(string $msisdn): array
    {
        $stmt = $this->db->prepare("SELECT msisdn, wallet_type, balance FROM mtn_wallets WHERE msisdn = ?");
        $stmt->execute([$msisdn]);
        $wallet = $stmt->fetch();
        return $wallet ? ['success' => true, 'data' => $wallet] : ['success' => false, 'message' => 'Wallet not found'];
    }
}

// ============================================================
// ROUTING
// ============================================================
try {
    $pdo = getDB();
    $participant = new MtnMomoParticipant($pdo);

    $action = $_GET['action'] ?? '';
    $rawBody = file_get_contents('php://input');
    $input = $_SERVER['REQUEST_METHOD'] === 'POST'
        ? (json_decode($rawBody, true) ?? [])
        : $_GET;
    $headers = getallheaders() ?: [];

    echo json_encode($participant->handleRequest($action, $input, $rawBody, $headers));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    error_log("[MTN MoMo] " . $e->getMessage());
}
