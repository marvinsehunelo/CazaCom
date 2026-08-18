<?php
// Cazacom: Release a previously placed hold (funds return to balance)
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../security/ApiAuthenticator.php';
use Security\ApiAuthenticator;

// Single Database connection reused for both auth and the queries
// below, same as hold.php / debit.php / credit.php.
$database = new Database();
$db = $database->getConnection();
if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// ============================================================
// REVERT: the previous "certificate-based authentication" block
// that lived here called verify_requester_signature($input, $db) —
// a function that is not defined anywhere in this codebase (no
// crypto.php, no equivalent helper). Every request to this endpoint
// fataled with "Call to undefined function verify_requester_signature()"
// while still returning HTTP 200, leaving multiple holds stuck in
// 'HELD' status with customer balances not restored.
//
// That block was also fixing the wrong problem. The
// "Invalid signature from: CAZACOM" error it was meant to address
// is thrown by AggregateSigner/SignatureVerifier on VouchMorph's
// side while verifying the SIGNATURE ON CAZACOM'S HOLD-PLACEMENT
// RESPONSE (in hold.php), during pool assembly — BEFORE any rollback
// or release_hold call happens. It has nothing to do with
// authenticating incoming requests to THIS endpoint. Every other
// CAZACOM endpoint in this family (hold.php, verify_wallet.php,
// balance.php) authenticates incoming requests via ApiAuthenticator
// alone, with no certificate/signature check, and that works fine.
// This endpoint now matches that same, working pattern.
//
// If CAZACOM's hold.php genuinely needs to start returning a signed
// response (to satisfy SignatureVerifier the way ZURUBANK/SACCUSSALIS
// already do), that is a separate change to hold.php's response, not
// to auth on this endpoint. Do not re-add signature verification
// here without first confirming that's actually where it belongs.
// ============================================================
$auth = new ApiAuthenticator($db);
$participant = $auth->requireAuth();
// requireAuth() already sends a 401 and exit()s internally on failure —
// execution only reaches here with a valid, authenticated $participant.
error_log("[CAZACOM release_hold] Authenticated via API key (participant: " . $participant . ")");

// TODO: no scope enforcement currently possible — ApiAuthenticator::requireAuth()
// returns only a participant name, not a scopes list. Restore a real
// check here once scopes are modeled, or confirm scope enforcement is
// intentionally not required for this endpoint.

// Get input data
$input = json_decode(file_get_contents("php://input"), true);

// ============================================================
// FIX: Extract hold_reference from multiple possible field names
// GenericBankClient::releaseHold() sends 'hold_reference'.
// Multi-source rollback may send 'hold_reference' or 'reference'.
// ============================================================
$holdReference = $input['hold_reference'] ?? $input['reference'] ?? null;
$reason = $input['reason'] ?? 'Released';
$assetType = $input['asset_type'] ?? null;
$sourceIdentifier = $input['source_identifier'] ?? $input['phone'] ?? null;

if (!$holdReference) {
    // FIX: was missing 'success' — same fail-closed-default gap
    // fixed across hold.php/credit.php/debit.php. Any response
    // GenericBankClient can't recognize now defaults to failure on
    // VouchMorph's side, so this MUST carry an explicit signal.
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => "Hold reference required",
        "released" => false
    ]);
    exit;
}

$db->beginTransaction();
try {
    // ============================================================
    // FIX: Log what we're looking for
    // ============================================================
    error_log("[CAZACOM release_hold] Looking for hold: " . $holdReference);
    error_log("[CAZACOM release_hold] Asset type: " . ($assetType ?? 'null'));
    error_log("[CAZACOM release_hold] Source identifier: " . ($sourceIdentifier ?? 'null'));

    // Lock the hold row — mirrors debit.php's FOR UPDATE lookup, and
    // only allows releasing a hold that is still actually HELD (not
    // already COMMITTED by debit.php or already RELEASED by a prior
    // call), so this can't double-release or release funds already
    // spent.
    $stmt = $db->prepare("
        SELECT * FROM financial_holds
        WHERE hold_reference = :ref AND status = 'HELD'
        FOR UPDATE
    ");
    $stmt->execute(['ref' => $holdReference]);
    $hold = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$hold) {
        // Check if hold exists but in different status
        $stmt2 = $db->prepare("
            SELECT * FROM financial_holds
            WHERE hold_reference = :ref
        ");
        $stmt2->execute(['ref' => $holdReference]);
        $existingHold = $stmt2->fetch(PDO::FETCH_ASSOC);

        if ($existingHold) {
            // Hold exists but not in HELD status - already committed or released
            error_log("[CAZACOM release_hold] Hold exists but status is: " . $existingHold['status']);

            if ($existingHold['status'] === 'RELEASED') {
                // Already released - treat as success
                $db->commit();
                echo json_encode([
                    "success" => true,
                    "status" => "success",
                    "released" => true,
                    "hold_reference" => $holdReference,
                    "amount_released" => (float)$existingHold['amount'],
                    "message" => "Hold already released",
                    "already_released" => true
                ]);
                exit;
            } elseif ($existingHold['status'] === 'COMMITTED' || $existingHold['status'] === 'DEBITED') {
                // Already debited - cannot release
                throw new Exception("Hold already committed/debited, cannot release");
            } else {
                throw new Exception("Hold exists but status is: " . $existingHold['status']);
            }
        }

        throw new Exception("Hold not found");
    }

    error_log("[CAZACOM release_hold] Found hold: user_id=" . $hold['user_id'] . ", amount=" . $hold['amount']);

    // Mark the hold released
    $stmt = $db->prepare("
        UPDATE financial_holds
        SET status = 'RELEASED',
            released_at = NOW(),
            release_reason = :reason
        WHERE hold_reference = :ref
    ");
    $stmt->execute([
        'reason' => $reason,
        'ref' => $holdReference
    ]);

    // Restore the held amount to the account's balance. This is the
    // direct reverse of hold.php's "balance = balance - :amount" —
    // since mobile_money_accounts has no held_balance column, hold.php
    // moved funds straight out of `balance` at hold time, so releasing
    // simply moves them straight back in.
    $stmt = $db->prepare("
        UPDATE mobile_money_accounts
        SET balance = balance + :amount,
            last_updated = NOW()
        WHERE user_id = :user_id
    ");
    $stmt->execute([
        'amount' => $hold['amount'],
        'user_id' => $hold['user_id']
    ]);

    if ($stmt->rowCount() === 0) {
        // Should not happen if hold.php's own debit succeeded originally
        // (same user_id), but guard against silently "succeeding" with
        // no account to credit back, same as credit.php's rowCount check.
        throw new Exception("No mobile money account found to release funds back to");
    }

    $db->commit();

    // Get updated balance
    $stmt = $db->prepare("SELECT balance FROM mobile_money_accounts WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $hold['user_id']]);
    $updatedWallet = $stmt->fetch(PDO::FETCH_ASSOC);
    $newBalance = $updatedWallet ? (float)$updatedWallet['balance'] : null;

    error_log("[CAZACOM release_hold] Released hold: " . $holdReference . ", amount: " . $hold['amount'] . ", new balance: " . $newBalance);

    echo json_encode([
        // FIX: was missing a top-level 'success' boolean — same gap
        // already fixed in hold.php/credit.php/debit.php. This file
        // only ever set 'status' (string) and 'released' (bool).
        // GenericBankClient::send() checks 'success' first, then the
        // action-specific 'released' flag for release_hold, then
        // 'status' === 'SUCCESS' (uppercase literal) as a last resort
        // — this file's lowercase 'success' string value never matched
        // that final fallback either, leaving genuinely successful
        // releases at risk of being misread as failures.
        "success" => true,
        "status" => "success",
        "released" => true,
        "hold_reference" => $holdReference,
        "amount_released" => (float)$hold['amount'],
        "new_balance" => $newBalance,
        "reason" => $reason,
        "user_id" => $hold['user_id']
    ]);

} catch (Exception $e) {
    $db->rollBack();
    error_log("[CAZACOM release_hold] ERROR: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => $e->getMessage(),
        "released" => false
    ]);
}
