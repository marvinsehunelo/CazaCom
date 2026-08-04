<?php
declare(strict_types=1);

/**
 * MTN MoMo Participant — single-file simulation, hosted on Cazacom's
 * infrastructure (technical_host_participant_id -> CAZACOM in
 * switch_participants), but MTN remains an independent DIRECT
 * participant with its own settlement account and API key.
 *
 * Method names deliberately mirror MTN MoMo's real public Open API
 * (Disbursement product) so swapping $sandboxMode to call the real
 * MTN sandbox later is a drop-in change, not a rewrite:
 *   - transfer()                 -> POST /disbursement/v1_0/transfer
 *   - getTransferStatus()        -> GET  /disbursement/v1_0/transfer/{referenceId}
 *   - validateAccountHolder()    -> GET  /disbursement/v1_0/accountholder/msisdn/{msisdn}/active
 *
 * Everything below transfer()/getTransferStatus() (agent cashout) is
 * VouchMorph/Cazacom business logic layered on top — MTN's real API
 * has no concept of "agent hands over physical cash", so that part
 * stays local regardless of sandbox vs production mode.
 *
 * Entry point: routes on ?action=, single file, no separate router.
 *   POST /mtn_momo_participant.php?action=switch_webhook   <- called BY centralswitch
 *   POST /mtn_momo_participant.php?action=initiate_cashout <- called by MTN app/USSD
 *   POST /mtn_momo_participant.php?action=confirm_cashout  <- called by agent app
 *   GET  /mtn_momo_participant.php?action=wallet_balance
 */

require_once __DIR__ . '/../config/db.php'; // reuse the same getDB()/$pdo from centralswitch, OR
                                             // point at MTN's own DB connection if it's separate infra.

header('Content-Type: application/json');

class MtnMomoParticipant
{
    private PDO $db;
    private bool $sandboxMode;

    // MTN MoMo Disbursement sandbox credentials — leave null while
    // fully simulating locally; fill these in once real sandbox
    // access is granted and flip $sandboxMode to 'REMOTE'.
    private ?string $subscriptionKey;
    private ?string $apiUser;
    private ?string $apiKey;
    private ?string $baseUrl;
    private ?string $cachedToken = null;
    private ?int $tokenExpiresAt = null;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        // 'LOCAL'  = simulate everything in mtn_wallets/mtn_transfers, no external calls.
        // 'REMOTE' = actually call MTN's sandbox/production Disbursement API.
        $this->sandboxMode = (getenv('MTN_MODE') ?: 'LOCAL') === 'LOCAL';

        $this->subscriptionKey = getenv('MTN_SUBSCRIPTION_KEY') ?: null;
        $this->apiUser = getenv('MTN_API_USER') ?: null;
        $this->apiKey = getenv('MTN_API_KEY') ?: null;
        $this->baseUrl = getenv('MTN_BASE_URL') ?: 'https://sandbox.momodeveloper.mtn.com';
    }

    // ============================================================
    // ENTRY POINT
    // ============================================================
    public function handleRequest(string $action, array $input): array
    {
        return match ($action) {
            'switch_webhook' => $this->handleSwitchWebhook($input),
            'initiate_cashout' => $this->initiateAgentCashout($input),
            'confirm_cashout' => $this->confirmAgentCashout($input),
            'wallet_balance' => $this->getWalletBalance($input['msisdn'] ?? ''),
            default => ['success' => false, 'message' => "Unknown action: {$action}"],
        };
    }

    // ============================================================
    // 1. SWITCH WEBHOOK — called by centralswitch when a transaction
    //    destined for MTN settles. This is where the switch's
    //    aggregate settlement movement gets fanned out to the
    //    ACTUAL customer wallet — the switch has no idea this
    //    customer-level ledger exists.
    // ============================================================
    private function handleSwitchWebhook(array $input): array
    {
        $required = ['transaction_reference', 'status', 'amount', 'currency', 'destination_account_number'];
        foreach ($required as $r) {
            if (empty($input[$r]) && $input[$r] !== 0) {
                return ['success' => false, 'message' => "Missing field: {$r}"];
            }
        }

        if ($input['status'] !== 'COMPLETED') {
            // Switch reported FAILED/REVERSED — nothing to credit.
            return ['success' => true, 'message' => 'Acknowledged, no wallet action for non-COMPLETED status'];
        }

        $referenceId = 'MTNXFER_' . hash('sha256', $input['transaction_reference']); // deterministic, idempotent

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
    //    This IS the "deposit" — credits a customer (or agent)
    //    wallet. Idempotent on reference_id.
    // ============================================================
    public function transfer(array $params): array
    {
        // Idempotency: same reference_id already processed -> return existing result.
        $stmt = $this->db->prepare("SELECT * FROM mtn_transfers WHERE reference_id = ?");
        $stmt->execute([$params['reference_id']]);
        $existing = $stmt->fetch();
        if ($existing) {
            return [
                'success' => true,
                'message' => 'Duplicate reference_id — returning existing transfer',
                'data' => $existing,
            ];
        }

        if ($this->sandboxMode) {
            return $this->localTransfer($params);
        }

        return $this->remoteTransfer($params);
    }

    /**
     * LOCAL simulation: credits mtn_wallets directly. Auto-creates
     * the wallet if the MSISDN has never been seen (mirrors a real
     * MoMo account being auto-provisioned on first credit in some
     * markets — adjust if MTN requires pre-registration instead).
     */
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

    /**
     * REMOTE: actual MTN MoMo Disbursement API call. Fill in once
     * real sandbox credentials exist. Kept structurally identical to
     * localTransfer()'s bookkeeping (same mtn_transfers insert) so
     * switching modes doesn't change anything callers see.
     */
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

        // MTN's transfer endpoint returns 202 Accepted immediately;
        // real status comes from a subsequent getTransferStatus() poll.
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

    // getTransferStatus() -> GET /disbursement/v1_0/transfer/{referenceId}
    public function getTransferStatus(string $referenceId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM mtn_transfers WHERE reference_id = ?");
        $stmt->execute([$referenceId]);
        $transfer = $stmt->fetch();
        return $transfer
            ? ['success' => true, 'data' => $transfer]
            : ['success' => false, 'message' => 'Reference not found'];
    }

    private function getAccessToken(): ?string
    {
        if ($this->cachedToken && $this->tokenExpiresAt > time()) {
            return $this->cachedToken;
        }
        if (!$this->apiUser || !$this->apiKey || !$this->subscriptionKey) {
            return null; // remote mode requested but credentials not configured
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
    // 3. AGENT CASHOUT — VouchMorph/local business logic, NOT part
    //    of MTN's real public API. Two-step: fund the agent's
    //    wallet, then agent confirms physical handover.
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

            // Move funds customer -> agent (internal, no switch involvement).
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

            // In production: SMS the cash_code to the customer here.
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
// ROUTING — single file, action param, no separate router file.
// ============================================================
try {
    $pdo = getDB();
    $participant = new MtnMomoParticipant($pdo);

    $action = $_GET['action'] ?? '';
    $input = $_SERVER['REQUEST_METHOD'] === 'POST'
        ? (json_decode(file_get_contents('php://input'), true) ?? [])
        : $_GET;

    echo json_encode($participant->handleRequest($action, $input));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    error_log("[MTN MoMo] " . $e->getMessage());
}
