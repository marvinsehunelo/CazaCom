<?php
declare(strict_types=1);

/**
 * MTN MoMo Participant — standalone HTTP receiver, deployed on Cazacom's
 * infrastructure as its own file. Deliberately self-contained: does
 * NOT require any VouchMorph-repo files (src/Infrastructure/...) —
 * this runs on Cazacom's server, which has no access to VouchMorph's
 * codebase. Auth verification is inlined here rather than importing
 * VouchMorph's AuthSchemeRegistry class.
 *
 * Covers all THREE things VouchMorph needs from MTN:
 *  - COLLECTION (requesttopay) — MTN as a SOURCE: verifyAsset/placeHold/
 *    debitFunds/releaseHold map onto MTN's real Collection product.
 *  - DISBURSEMENT (transfer) — MTN as a DESTINATION: processDeposit maps
 *    onto MTN's real Disbursement product. Already working, unchanged
 *    in behavior from the earlier version of this file.
 *  - Agent cash-out — VouchMorph-local business logic, not part of
 *    MTN's real public API (MoMo has no ATM network).
 *
 * Auth: accepts EITHER centralswitch's HMAC scheme OR VouchMorph's
 * API-key scheme on every endpoint — same "please everyone" pattern
 * used elsewhere in this system, now self-contained without external
 * dependencies.
 *
 * $sandboxMode ('LOCAL' vs 'REMOTE') controls whether this simulates
 * everything in mtn_wallets/mtn_collections/mtn_transfers, or actually
 * calls MTN's Disbursement + Collection sandbox/production APIs.
 *
 * FIX (this revision): the 'wallet_balance' action read
 * $input['msisdn'], a field that is never actually sent by
 * VouchMorph's GenericBankClient — it sends 'source_identifier'
 * (plus aliases 'phone', 'wallet_phone', 'account_number', etc via
 * addSourceIdentifier(), but never 'msisdn'). Every real balance
 * check therefore looked up an empty string and returned "Wallet not
 * found" regardless of whether the MSISDN actually existed in
 * mtn_wallets — confirmed live for +26779000000 through +26779000004,
 * all of which have real, non-zero balances in mtn_wallets. The
 * lookup itself (getWalletBalanceAction) was always correct; only the
 * field name pulled from $input was wrong.
 */

require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

class MtnMomoParticipant
{
    private PDO $db;
    private bool $sandboxMode;

    // Disbursement credentials
    private ?string $disbSubscriptionKey;
    private ?string $disbApiUser;
    private ?string $disbApiKey;
    private ?string $disbToken = null;
    private ?int $disbTokenExpiresAt = null;

    // Collection credentials (separate product, separate subscription key on real MTN)
    private ?string $collSubscriptionKey;
    private ?string $collApiUser;
    private ?string $collApiKey;
    private ?string $collToken = null;
    private ?int $collTokenExpiresAt = null;

    private ?string $baseUrl;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->sandboxMode = (getenv('MTN_MODE') ?: 'LOCAL') === 'LOCAL';

        $this->disbSubscriptionKey = getenv('MTN_DISBURSEMENT_SUBSCRIPTION_KEY') ?: null;
        $this->disbApiUser = getenv('MTN_DISBURSEMENT_API_USER') ?: null;
        $this->disbApiKey = getenv('MTN_DISBURSEMENT_API_KEY') ?: null;

        $this->collSubscriptionKey = getenv('MTN_COLLECTION_SUBSCRIPTION_KEY') ?: null;
        $this->collApiUser = getenv('MTN_COLLECTION_API_USER') ?: null;
        $this->collApiKey = getenv('MTN_COLLECTION_API_KEY') ?: null;

        $this->baseUrl = getenv('MTN_BASE_URL') ?: 'https://sandbox.momodeveloper.mtn.com';
    }

    // ============================================================
    // ENTRY POINT / ROUTING
    // ============================================================
    public function handleRequest(string $action, array $input, string $rawBody, array $headers): array
    {
        // switch_webhook is called BY centralswitch and remains as-is
        if ($action === 'switch_webhook') {
            return $this->handleSwitchWebhook($input, $rawBody, $headers);
        }

        // Every other action requires standard dual-auth (VouchMorph API key
        // OR centralswitch HMAC) — this file is a shared receiver for both.
        if (!$this->verifyIncomingAuth($rawBody, $headers)) {
            http_response_code(401);
            return ['success' => false, 'message' => 'Authentication failed'];
        }

        return match ($action) {
            'verify_asset' => $this->verifyAsset($input),
            'place_hold' => $this->placeHold($input),
            'debit_funds' => $this->debitFunds($input),
            'release_hold' => $this->releaseHold($input),
            'process_deposit' => $this->processDeposit($input),
            'check_status' => $this->checkStatus($input['reference'] ?? ''),
            'initiate_cashout' => $this->initiateAgentCashout($input),
            'confirm_cashout' => $this->confirmAgentCashout($input),
            // FIX: was $input['msisdn'] ?? '' — that key is never sent
            // by GenericBankClient. It sends 'source_identifier' plus
            // aliases 'phone'/'wallet_phone'/'account_number'/etc, but
            // never literally 'msisdn'. Every real balance check was
            // therefore looking up an empty string. Now checks all the
            // field names GenericBankClient::addSourceIdentifier()
            // actually populates, still preferring 'msisdn' first for
            // any caller that does send it directly.
            'wallet_balance' => $this->getWalletBalanceAction(
                $input['msisdn']
                    ?? $input['source_identifier']
                    ?? $input['phone']
                    ?? $input['wallet_phone']
                    ?? $input['account_number']
                    ?? ''
            ),
            default => ['success' => false, 'message' => "Unknown action: {$action}"],
        };
    }

    /**
     * Self-contained dual-auth check — no cross-repo class dependency.
     * Accepts EITHER VouchMorph's API key OR centralswitch's HMAC,
     * checked inline rather than via VouchMorph's AuthSchemeRegistry
     * (which doesn't exist in this repo).
     */
    private function verifyIncomingAuth(string $rawBody, array $headers): bool
    {
        $headersLower = array_change_key_case($headers, CASE_LOWER);

        // Path 1: VouchMorph API key
        $providedKey = $headersLower['x-api-key'] ?? null;
        $expectedKey = getenv('MTN_VOUCHMORPH_API_KEY') ?: '';
        if ($providedKey && $expectedKey && hash_equals($expectedKey, $providedKey)) {
            error_log("[MTN MoMo] Authenticated via VOUCHMORPH API key");
            return true;
        }

        // Path 2: centralswitch HMAC shared secret
        $timestamp = $headersLower['x-api-timestamp'] ?? null;
        $signature = $headersLower['x-api-signature'] ?? null;
        $secret = getenv('MTN_SWITCH_SECRET') ?: '';

        if ($timestamp && $signature && $secret) {
            if (abs(time() - (int)$timestamp) <= 300) {
                $expected = hash_hmac('sha256', $timestamp . $rawBody, $secret);
                if (hash_equals($expected, $signature)) {
                    error_log("[MTN MoMo] Authenticated via CENTRALSWITCH HMAC");
                    return true;
                }
            }
        }

        return false;
    }

    // ============================================================
    // 1. COLLECTION — MTN AS SOURCE
    //    Real MTN API: POST /collection/v1_0/requesttopay,
    //                   GET  /collection/v1_0/requesttopay/{referenceId}
    // ============================================================

    /**
     * verifyAsset — checks the payer's wallet exists/is active.
     * Real MTN equivalent: GET /collection/v1_0/accountholder/msisdn/{msisdn}/active
     * LOCAL mode: checks mtn_wallets directly.
     */
    public function verifyAsset(array $payload): array
    {
        $msisdn = $payload['source_identifier'] ?? $payload['phone'] ?? $payload['wallet_phone'] ?? null;
        if (!$msisdn) {
            return ['success' => false, 'verified' => false, 'message' => 'source_identifier (MSISDN) required'];
        }

        if ($this->sandboxMode) {
            $stmt = $this->db->prepare("SELECT balance, status FROM mtn_wallets WHERE msisdn = ? AND wallet_type = 'CUSTOMER'");
            $stmt->execute([$msisdn]);
            $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$wallet) {
                // Auto-provision for sandbox testing, mirroring how MTN's
                // real sandbox test MSISDNs are pre-seeded active.
                $this->db->prepare("INSERT INTO mtn_wallets (msisdn, wallet_type, balance) VALUES (?, 'CUSTOMER', 0)")->execute([$msisdn]);
                $wallet = ['balance' => 0, 'status' => 'ACTIVE'];
            }

            $active = ($wallet['status'] ?? 'ACTIVE') === 'ACTIVE';
            $amount = (float)($payload['amount'] ?? 0);
            $hasSufficientBalance = (float)$wallet['balance'] >= $amount;

            return [
                'success' => $active,
                'verified' => $active,
                'balance' => (float)$wallet['balance'],
                'currency' => $payload['currency'] ?? 'BWP',
                'message' => !$active ? 'Wallet not active' : ($hasSufficientBalance ? 'Verified' : 'Verified — balance may be insufficient, real check happens at approval'),
                'data' => [],
            ];
        }

        return $this->remoteCollectionAccountActive($msisdn);
    }

    /**
     * placeHold — initiates MTN Collection requesttopay, then polls a
     * BOUNDED number of times for approval. See the class docblock for
     * why this is a real limitation, not a full solution, for slow
     * real-world customer approval.
     */
    public function placeHold(array $payload): array
    {
        $msisdn = $payload['source_identifier'] ?? $payload['phone'] ?? $payload['wallet_phone'] ?? null;
        $amount = (float)($payload['amount'] ?? 0);
        $currency = $payload['currency'] ?? 'BWP';
        $referenceId = 'MTNCOLL_' . ($payload['reference'] ?? uniqid());

        if (!$msisdn || $amount <= 0) {
            return [
                'success' => false,
                'hold_placed' => false,
                'message' => 'source_identifier and amount required'
            ];
        }

        // Idempotency: same reference already has a collection record
        $stmt = $this->db->prepare("SELECT * FROM mtn_collections WHERE reference_id = ?");
        $stmt->execute([$referenceId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            $this->db->prepare("
                INSERT INTO mtn_collections (reference_id, payer_msisdn, amount, currency, status, payer_message, payee_note, hold_reference)
                VALUES (?, ?, ?, ?, 'PENDING', ?, ?, ?)
            ")->execute([
                $referenceId, $msisdn, $amount, $currency,
                $payload['hold_reason'] ?? 'VouchMorph swap', 'Collection hold', $referenceId,
            ]);
        }

        if ($this->sandboxMode) {
            // LOCAL sandbox: auto-approve immediately by moving amount into
            // a HELD state on the wallet (debit now, matching what a real
            // approved requesttopay does — customer's balance moves at
            // approval time, not at debitFunds() time).
            $this->db->beginTransaction();
            try {
                $stmt = $this->db->prepare("SELECT wallet_id, balance FROM mtn_wallets WHERE msisdn = ? FOR UPDATE");
                $stmt->execute([$msisdn]);
                $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$wallet || (float)$wallet['balance'] < $amount) {
                    $this->db->prepare("UPDATE mtn_collections SET status = 'FAILED' WHERE reference_id = ?")->execute([$referenceId]);
                    $this->db->commit();
                    return [
                        'success' => false,
                        'hold_placed' => false,
                        'message' => 'Insufficient MTN wallet balance'
                    ];
                }

                $this->db->prepare("UPDATE mtn_wallets SET balance = balance - ? WHERE wallet_id = ?")
                    ->execute([$amount, $wallet['wallet_id']]);
                $this->db->prepare("UPDATE mtn_collections SET status = 'SUCCESSFUL', completed_at = NOW() WHERE reference_id = ?")
                    ->execute([$referenceId]);

                $this->db->commit();

                return [
                    // FIX: was missing this key — this exact message
                    // ("Collection approved (simulated) — funds held")
                    // is the literal text seen in the "Hold failed: ..."
                    // contradiction this session. Same root cause as
                    // absa_participant.php and CAZACOM's hold.php.
                    'success' => true,
                    'hold_placed' => true,
                    'hold_reference' => $referenceId,
                    'status' => 'ACTIVE',
                    'message' => 'Collection approved (simulated) — funds held',
                    'data' => [],
                ];
            } catch (Exception $e) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'hold_placed' => false,
                    'message' => 'Collection failed: ' . $e->getMessage()
                ];
            }
        }

        // REMOTE mode: submit real requesttopay, then poll a bounded
        // number of times (NOT indefinitely — see class docblock).
        $submitted = $this->remoteInitiateCollection($referenceId, $msisdn, $amount, $currency, $payload);
        if (!$submitted) {
            return [
                'success' => false,
                'hold_placed' => false,
                'message' => 'Failed to submit collection request to MTN'
            ];
        }

        $maxAttempts = (int)(getenv('MTN_COLLECTION_POLL_ATTEMPTS') ?: 5);
        $delaySeconds = (int)(getenv('MTN_COLLECTION_POLL_DELAY_SECONDS') ?: 2);

        for ($i = 0; $i < $maxAttempts; $i++) {
            sleep($delaySeconds);
            $status = $this->remoteCollectionStatus($referenceId);

            if ($status === 'SUCCESSFUL') {
                $this->db->prepare("UPDATE mtn_collections SET status = 'SUCCESSFUL', completed_at = NOW() WHERE reference_id = ?")
                    ->execute([$referenceId]);
                return [
                    // FIX: added success key for remote poll success branch
                    'success' => true,
                    'hold_placed' => true,
                    'hold_reference' => $referenceId,
                    'status' => 'ACTIVE',
                    'message' => 'Collection approved by customer'
                ];
            }
            if ($status === 'FAILED') {
                $this->db->prepare("UPDATE mtn_collections SET status = 'FAILED' WHERE reference_id = ?")->execute([$referenceId]);
                return [
                    'success' => false,
                    'hold_placed' => false,
                    'message' => 'Customer rejected or collection failed'
                ];
            }
            // still PENDING — keep polling within the bounded window
        }

        // Timed out waiting for approval — genuinely still pending, not
        // failed. Caller (SwapService) currently has no way to represent
        // this state — it will be treated as a hold failure. Flagging
        // this as the real architectural gap described in the class docblock.
        error_log("[MTN MoMo] Collection {$referenceId} still PENDING after {$maxAttempts} poll attempts — customer has not approved in time. This needs async/webhook support in SwapService to handle correctly.");
        return [
            'success' => false,
            'hold_placed' => false,
            'message' => 'Customer has not approved the collection request in time. They may still approve it later — this swap cannot proceed synchronously.'
        ];
    }

    /**
     * debitFunds — finalizes an already-approved collection. In MTN's
     * real model, the customer's money already moved to VouchMorph's
     * MTN collection account at approval time (inside placeHold above)
     * — this just marks VouchMorph's own record as finalized.
     */
    public function debitFunds(array $payload): array
    {
        $referenceId = $payload['hold_reference'] ?? $payload['reference'] ?? null;
        if (!$referenceId) {
            return [
                'success' => false,
                'debited' => false,
                'message' => 'hold_reference required'
            ];
        }

        $stmt = $this->db->prepare("SELECT * FROM mtn_collections WHERE reference_id = ?");
        $stmt->execute([$referenceId]);
        $collection = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$collection) {
            return [
                'success' => false,
                'debited' => false,
                'message' => 'Collection not found'
            ];
        }
        if ($collection['status'] !== 'SUCCESSFUL') {
            return [
                'success' => false,
                'debited' => false,
                'message' => "Collection status is {$collection['status']}, cannot finalize"
            ];
        }

        return [
            // FIX: added success key — debit success return had no signal at all
            'success' => true,
            'debited' => true,
            'transaction_reference' => $referenceId,
            'message' => 'Collection finalized',
            'data' => [],
        ];
    }

    /**
     * releaseHold — reverses an approved-but-not-yet-debited collection
     * by crediting the customer's wallet back. Real MTN Collection has
     * no reversal endpoint for an already-approved requesttopay — this
     * is VouchMorph's own compensating transfer, same pattern as a
     * bank hold release.
     */
    public function releaseHold(array $payload): array
    {
        $referenceId = $payload['hold_reference'] ?? null;
        if (!$referenceId) {
            return [
                'success' => false,
                'released' => false,
                'message' => 'hold_reference required'
            ];
        }

        $stmt = $this->db->prepare("SELECT * FROM mtn_collections WHERE reference_id = ?");
        $stmt->execute([$referenceId]);
        $collection = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$collection) {
            return [
                'success' => false,
                'released' => false,
                'message' => 'Collection not found'
            ];
        }
        if ($collection['status'] !== 'SUCCESSFUL') {
            // Nothing to reverse — never actually collected, or already cancelled.
            $this->db->prepare("UPDATE mtn_collections SET status = 'CANCELLED' WHERE reference_id = ?")->execute([$referenceId]);
            // FIX: added success key to release hold success branch
            return [
                'success' => true,
                'released' => true,
                'message' => 'Collection was not yet successful — marked cancelled, no reversal needed'
            ];
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT wallet_id FROM mtn_wallets WHERE msisdn = ? FOR UPDATE");
            $stmt->execute([$collection['payer_msisdn']]);
            $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($wallet) {
                $this->db->prepare("UPDATE mtn_wallets SET balance = balance + ? WHERE wallet_id = ?")
                    ->execute([$collection['amount'], $wallet['wallet_id']]);
            }

            $this->db->prepare("UPDATE mtn_collections SET status = 'CANCELLED' WHERE reference_id = ?")->execute([$referenceId]);
            $this->db->commit();

            // FIX: added success key to release hold success branch
            return [
                'success' => true,
                'released' => true,
                'message' => 'Collection reversed, customer refunded'
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'released' => false,
                'message' => 'Release failed: ' . $e->getMessage()
            ];
        }
    }

    private function remoteCollectionAccountActive(string $msisdn): array
    {
        $token = $this->getCollectionToken();
        if (!$token) return ['success' => false, 'verified' => false, 'message' => 'Could not obtain MTN Collection token'];

        $ch = curl_init($this->baseUrl . "/collection/v1_0/accountholder/msisdn/{$msisdn}/active");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'X-Target-Environment: sandbox',
                'Ocp-Apim-Subscription-Key: ' . $this->collSubscriptionKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['success' => $httpCode === 200, 'verified' => $httpCode === 200, 'message' => "MTN responded HTTP {$httpCode}"];
    }

    private function remoteInitiateCollection(string $referenceId, string $msisdn, float $amount, string $currency, array $payload): bool
    {
        $token = $this->getCollectionToken();
        if (!$token) return false;

        $body = json_encode([
            'amount' => (string)$amount,
            'currency' => $currency,
            'externalId' => $referenceId,
            'payer' => ['partyIdType' => 'MSISDN', 'partyId' => $msisdn],
            'payerMessage' => $payload['hold_reason'] ?? 'VouchMorph collection',
            'payeeNote' => 'VouchMorph',
        ]);

        $ch = curl_init($this->baseUrl . '/collection/v1_0/requesttopay');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'X-Reference-Id: ' . $referenceId,
                'X-Target-Environment: sandbox',
                'Ocp-Apim-Subscription-Key: ' . $this->collSubscriptionKey,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 202;
    }

    private function remoteCollectionStatus(string $referenceId): string
    {
        $token = $this->getCollectionToken();
        if (!$token) return 'FAILED';

        $ch = curl_init($this->baseUrl . "/collection/v1_0/requesttopay/{$referenceId}");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'X-Target-Environment: sandbox',
                'Ocp-Apim-Subscription-Key: ' . $this->collSubscriptionKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        return $data['status'] ?? 'PENDING';
    }

    private function getCollectionToken(): ?string
    {
        if ($this->collToken && $this->collTokenExpiresAt > time()) return $this->collToken;
        if (!$this->collApiUser || !$this->collApiKey || !$this->collSubscriptionKey) return null;

        $ch = curl_init($this->baseUrl . '/collection/token/');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_HTTPHEADER => [
                'Ocp-Apim-Subscription-Key: ' . $this->collSubscriptionKey,
                'Authorization: Basic ' . base64_encode($this->collApiUser . ':' . $this->collApiKey),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) return null;

        $this->collToken = $data['access_token'];
        $this->collTokenExpiresAt = time() + (int)($data['expires_in'] ?? 3600) - 60;
        return $this->collToken;
    }

    // ============================================================
    // 2. DISBURSEMENT — MTN AS DESTINATION (unchanged behavior from
    //    the earlier version of this file, now expressed through the
    //    real BankAPIInterface method names instead of a bespoke
    //    transfer()-only shape).
    // ============================================================

    public function processDeposit(array $payload): array
    {
        $msisdn = $payload['destination_identifier'] ?? $payload['phone'] ?? $payload['wallet_phone'] ?? null;
        $amount = (float)($payload['amount'] ?? 0);
        $currency = $payload['currency'] ?? 'BWP';
        $referenceId = 'MTNXFER_' . ($payload['reference'] ?? uniqid());

        if (!$msisdn || $amount <= 0) {
            return ['credited' => false, 'message' => 'destination_identifier and amount required'];
        }

        $stmt = $this->db->prepare("SELECT * FROM mtn_transfers WHERE reference_id = ?");
        $stmt->execute([$referenceId]);
        if ($existing = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return ['credited' => true, 'transaction_reference' => $referenceId, 'message' => 'Duplicate reference — already processed', 'data' => $existing];
        }

        if ($this->sandboxMode) {
            $this->db->beginTransaction();
            try {
                $stmt = $this->db->prepare("SELECT wallet_id FROM mtn_wallets WHERE msisdn = ? FOR UPDATE");
                $stmt->execute([$msisdn]);
                $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$wallet) {
                    $stmt = $this->db->prepare("INSERT INTO mtn_wallets (msisdn, wallet_type, balance) VALUES (?, 'CUSTOMER', 0) RETURNING wallet_id");
                    $stmt->execute([$msisdn]);
                    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
                }

                $this->db->prepare("UPDATE mtn_wallets SET balance = balance + ? WHERE wallet_id = ?")->execute([$amount, $wallet['wallet_id']]);

                $stmt = $this->db->prepare("
                    INSERT INTO mtn_transfers (reference_id, switch_transaction_reference, payee_msisdn, amount, currency, status, payer_message, payee_note, completed_at)
                    VALUES (?, ?, ?, ?, ?, 'SUCCESSFUL', ?, ?, NOW()) RETURNING *
                ");
                $stmt->execute([$referenceId, $payload['reference'] ?? null, $msisdn, $amount, $currency, $payload['payer_message'] ?? '', $payload['payee_note'] ?? '']);
                $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

                $this->db->commit();
                return [
                    // FIX: added success key to LOCAL sandbox success branch
                    'success' => true,
                    'credited' => true,
                    'transaction_reference' => $referenceId,
                    'message' => 'Deposit successful',
                    'data' => $transfer
                ];
            } catch (Exception $e) {
                $this->db->rollBack();
                return ['credited' => false, 'message' => 'Deposit failed: ' . $e->getMessage()];
            }
        }

        return $this->remoteDisbursementTransfer($referenceId, $msisdn, $amount, $currency, $payload);
    }

    private function remoteDisbursementTransfer(string $referenceId, string $msisdn, float $amount, string $currency, array $payload): array
    {
        $token = $this->getDisbursementToken();
        if (!$token) return ['credited' => false, 'message' => 'Could not obtain MTN Disbursement token'];

        $body = json_encode([
            'amount' => (string)$amount,
            'currency' => $currency,
            'externalId' => $referenceId,
            'payee' => ['partyIdType' => 'MSISDN', 'partyId' => $msisdn],
            'payerMessage' => $payload['payer_message'] ?? '',
            'payeeNote' => $payload['payee_note'] ?? '',
        ]);

        $ch = curl_init($this->baseUrl . '/disbursement/v1_0/transfer');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'X-Reference-Id: ' . $referenceId,
                'X-Target-Environment: sandbox',
                'Ocp-Apim-Subscription-Key: ' . $this->disbSubscriptionKey,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $status = $httpCode === 202 ? 'PENDING' : 'FAILED';
        $stmt = $this->db->prepare("
            INSERT INTO mtn_transfers (reference_id, payee_msisdn, amount, currency, status)
            VALUES (?, ?, ?, ?, ?) RETURNING *
        ");
        $stmt->execute([$referenceId, $msisdn, $amount, $currency, $status]);

        return ['credited' => $httpCode === 202, 'transaction_reference' => $referenceId, 'message' => "MTN responded HTTP {$httpCode}"];
    }

    private function getDisbursementToken(): ?string
    {
        if ($this->disbToken && $this->disbTokenExpiresAt > time()) return $this->disbToken;
        if (!$this->disbApiUser || !$this->disbApiKey || !$this->disbSubscriptionKey) return null;

        $ch = curl_init($this->baseUrl . '/disbursement/token/');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_HTTPHEADER => [
                'Ocp-Apim-Subscription-Key: ' . $this->disbSubscriptionKey,
                'Authorization: Basic ' . base64_encode($this->disbApiUser . ':' . $this->disbApiKey),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) return null;

        $this->disbToken = $data['access_token'];
        $this->disbTokenExpiresAt = time() + (int)($data['expires_in'] ?? 3600) - 60;
        return $this->disbToken;
    }

    // ============================================================
    // 3. SWITCH WEBHOOK — unchanged from earlier version
    // ============================================================
    private function handleSwitchWebhook(array $input, string $rawBody, array $headers): array
    {
        if (!$this->verifyIncomingAuth($rawBody, $headers)) {
            http_response_code(401);
            return ['success' => false, 'message' => 'Invalid signature'];
        }

        $required = ['transaction_reference', 'status'];
        foreach ($required as $r) {
            if (empty($input[$r])) return ['success' => false, 'message' => "Missing field: {$r}"];
        }
        if ($input['status'] !== 'COMPLETED') {
            return ['success' => true, 'message' => 'Acknowledged, no wallet action for non-COMPLETED status'];
        }
        if (empty($input['amount']) || empty($input['currency']) || empty($input['destination_account_number'])) {
            error_log("[MTN MoMo] Webhook verified but missing amount/currency/destination_account_number for {$input['transaction_reference']}");
            return ['success' => true, 'message' => 'Signature verified, payload incomplete for wallet credit'];
        }

        return $this->processDeposit([
            'reference' => $input['transaction_reference'],
            'destination_identifier' => $input['destination_account_number'],
            'amount' => (float)$input['amount'],
            'currency' => $input['currency'],
            'payer_message' => 'Switch settlement',
            'payee_note' => "Credit for {$input['transaction_reference']}",
        ]);
    }

    // ============================================================
    // 4. AGENT CASH-OUT — unchanged from earlier version
    // ============================================================
    public function initiateAgentCashout(array $params): array
    {
        $required = ['customer_msisdn', 'agent_msisdn', 'amount', 'currency'];
        foreach ($required as $r) {
            if (empty($params[$r]) && $params[$r] !== 0) return ['success' => false, 'message' => "Missing field: {$r}"];
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT wallet_id, balance FROM mtn_wallets WHERE msisdn = ? FOR UPDATE");
            $stmt->execute([$params['customer_msisdn']]);
            $customerWallet = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customerWallet || $customerWallet['balance'] < $params['amount']) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Insufficient customer wallet balance'];
            }

            $stmt = $this->db->prepare("SELECT wallet_id FROM mtn_wallets WHERE msisdn = ? AND wallet_type = 'AGENT' FOR UPDATE");
            $stmt->execute([$params['agent_msisdn']]);
            $agentWallet = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$agentWallet) {
                $stmt = $this->db->prepare("INSERT INTO mtn_wallets (msisdn, wallet_type, balance) VALUES (?, 'AGENT', 0) RETURNING wallet_id");
                $stmt->execute([$params['agent_msisdn']]);
                $agentWallet = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            $this->db->prepare("UPDATE mtn_wallets SET balance = balance - ? WHERE wallet_id = ?")->execute([$params['amount'], $customerWallet['wallet_id']]);
            $this->db->prepare("UPDATE mtn_wallets SET balance = balance + ? WHERE wallet_id = ?")->execute([$params['amount'], $agentWallet['wallet_id']]);

            $cashoutRef = 'CASHOUT' . date('YmdHis') . rand(1000, 9999);
            $cashCode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $stmt = $this->db->prepare("
                INSERT INTO mtn_agent_cashouts (cashout_reference, customer_msisdn, agent_msisdn, amount, currency, status, cash_code, expires_at)
                VALUES (?, ?, ?, ?, ?, 'FUNDED', ?, NOW() + INTERVAL '30 minutes') RETURNING *
            ");
            $stmt->execute([$cashoutRef, $params['customer_msisdn'], $params['agent_msisdn'], $params['amount'], $params['currency'], $cashCode]);
            $cashout = $stmt->fetch(PDO::FETCH_ASSOC);

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
        $cashout = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cashout) return ['success' => false, 'message' => 'Cashout not found'];
        if ($cashout['status'] !== 'FUNDED') return ['success' => false, 'message' => "Cashout is not awaiting handoff (status: {$cashout['status']})"];
        if (strtotime($cashout['expires_at']) < time()) {
            $this->db->prepare("UPDATE mtn_agent_cashouts SET status = 'EXPIRED' WHERE cashout_id = ?")->execute([$cashout['cashout_id']]);
            return ['success' => false, 'message' => 'Cashout code expired'];
        }
        if (!hash_equals($cashout['cash_code'], $params['cash_code'])) {
            return ['success' => false, 'message' => 'Incorrect cash code'];
        }

        $this->db->prepare("UPDATE mtn_agent_cashouts SET status = 'COMPLETED', completed_at = NOW() WHERE cashout_id = ?")->execute([$cashout['cashout_id']]);
        return ['success' => true, 'message' => 'Cash handed over — cashout completed'];
    }

    private function getWalletBalanceAction(string $msisdn): array
    {
        $stmt = $this->db->prepare("SELECT msisdn, wallet_type, balance FROM mtn_wallets WHERE msisdn = ?");
        $stmt->execute([$msisdn]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        return $wallet ? ['success' => true, 'data' => $wallet] : ['success' => false, 'message' => 'Wallet not found'];
    }

    public function checkStatus(string $reference): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM mtn_collections WHERE reference_id = ? 
            UNION 
            SELECT reference_id, NULL, payee_msisdn as payer_msisdn, amount, currency, status, payer_message, payee_note, NULL as hold_reference, created_at, completed_at 
            FROM mtn_transfers WHERE reference_id = ?
        ");
        $stmt->execute([$reference, $reference]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? ['success' => true, 'data' => $result] : ['success' => false, 'message' => 'Reference not found'];
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
    $input = $_SERVER['REQUEST_METHOD'] === 'POST' ? (json_decode($rawBody, true) ?? []) : $_GET;
    $headers = getallheaders() ?: [];

    echo json_encode($participant->handleRequest($action, $input, $rawBody, $headers));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    error_log("[MTN MoMo] " . $e->getMessage());
}
