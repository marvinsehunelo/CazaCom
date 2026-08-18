<?php
// Cazacom: Credit funds to wallet (for incoming swaps)
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../security/ApiAuthenticator.php';
use Security\ApiAuthenticator;
// FIX: was `new PDO(getenv('DATABASE_URL'))` — not a valid PDO DSN.
// Single Database connection reused for both auth and the queries
// below, same as hold.php / debit.php.
$database = new Database();
$db = $database->getConnection();
if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Database connection failed']);
    exit;
}
$auth = new ApiAuthenticator($db);
$participant = $auth->requireAuth();
// requireAuth() already sends a 401 and exit()s internally on failure —
// execution only reaches here with a valid, authenticated $participant.

// ============================================================
// FIX: was `if (!in_array('initiate_payment', $client['scopes']))`.
// Same leftover-variable bug as hold.php — $client was never defined
// in this version of the file, a holdover from before this endpoint
// was switched from `authenticate()` to `requireAuth()`. See
// hold.php's matching fix for the full explanation. requireAuth() in
// the current ApiAuthenticator returns only a participant name, no
// scopes list, so there is no real data here to check against.
// Disabled with a TODO rather than fabricated — see hold.php's
// identical comment for the design question this leaves open.
// ============================================================
// TODO: no scope enforcement currently possible — ApiAuthenticator::requireAuth()
// returns only a participant name, not a scopes list. Restore a real
// check here once scopes are modeled, or confirm scope enforcement is
// intentionally not required for this endpoint.

$input = json_decode(file_get_contents("php://input"), true);
$reference = $input['reference'] ?? null;

// ============================================================
// FIX: was `$input['destination_id'] ?? null` — GenericBankClient
// never sends a field called 'destination_id'. SwapService's deposit
// payloads populate 'destination_identifier' (and, for WALLET asset
// types specifically, also 'phone'/'wallet_phone'/'beneficiary_phone'
// — see SwapService::processDepositWithProof()), but never
// 'destination_id'. Every real deposit therefore hit this file's own
// upfront required-fields guard immediately and failed with "Missing
// required fields" before ever reaching the database — confirmed live
// across every CAZACOM-as-destination swap this session. Now checks
// the field names actually sent, with 'destination_id' kept as a
// last-resort fallback for any caller that does send it directly.
// ============================================================
$destinationId = $input['destination_identifier']
    ?? $input['phone']
    ?? $input['wallet_phone']
    ?? $input['beneficiary_phone']
    ?? $input['destination_id']
    ?? null;

$amount = (float)($input['amount'] ?? 0);
$sourceHoldReference = $input['source_hold_reference'] ?? null;
if (!$reference || !$destinationId || $amount <= 0) {
    echo json_encode(["success" => false, "status" => "error", "message" => "Missing required fields"]);
    exit;
}
$db->beginTransaction();
try {
    // Get user by phone
    $stmt = $db->prepare("SELECT id FROM users WHERE phone_number = :phone");
    $stmt->execute(['phone' => $destinationId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new Exception("User not found");
    }
    // Credit wallet
    $stmt = $db->prepare("
        UPDATE mobile_money_accounts
        SET balance = balance + :amount,
            last_updated = NOW()
        WHERE user_id = :user_id
    ");
    $stmt->execute(['amount' => $amount, 'user_id' => $user['id']]);
    if ($stmt->rowCount() === 0) {
        // No mobile_money_accounts row exists for this user at all —
        // the UPDATE silently affected zero rows rather than erroring,
        // which would otherwise mask a missing account the same way
        // the earlier "Undefined column" bug masked a schema mismatch.
        throw new Exception("No mobile money account found for this user");
    }
    // Record transaction
    $transactionRef = 'CREDIT-' . $reference;
    // ============================================================
    // FIX: was inserting a 'source_reference' column that does not
    // exist on mobile_money_transactions. Real schema: id, user_id,
    // type, amount, fee, reference, recipient_phone, network, status,
    // wallet_type, created_at, completed_at — no source_reference.
    // Every real deposit through this file was therefore failing with
    // SQLSTATE[42703] before the transaction could commit, even though
    // the wallet credit UPDATE just above had already succeeded —
    // confirmed live as "Deposit failed: ... column source_reference
    // ... does not exist" across every CAZACOM-as-destination swap.
    // $sourceHoldReference isn't dropped silently: it's still passed
    // through untouched in the caller's payload and available via
    // $reference/$transactionRef for tracing; there's simply no column
    // on this table to persist it into directly.
    // ============================================================
    $stmt = $db->prepare("
        INSERT INTO mobile_money_transactions
        (user_id, type, amount, reference, recipient_phone, status, completed_at)
        VALUES (:user_id, 'credit', :amount, :ref, :recipient_phone, 'completed', NOW())
    ");
    $stmt->execute([
        'user_id' => $user['id'],
        'amount' => $amount,
        'ref' => $transactionRef,
        'recipient_phone' => $destinationId
    ]);
    $db->commit();
    // Get new balance
    $stmt = $db->prepare("SELECT balance FROM mobile_money_accounts WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user['id']]);
    $newBalance = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode([
        // FIX: was missing a top-level 'success' boolean — every other
        // working endpoint this session (absa_participant.php,
        // mtn_momo_participant.php, ZURUBANK's hold.php/balance.php,
        // verify_wallet.php) uses 'success' as its primary boolean key.
        // This file only ever set 'status' (string) and 'processed'
        // (bool), which would read as failure if the caller checks
        // $result['success'] ?? false — a very plausible explanation
        // for "Deposit failed: Deposit failed" showing up on every
        // CAZACOM-as-destination test, since that generic fallback
        // text would fire regardless of this file's real outcome.
        "success" => true,
        "status" => "success",
        "processed" => true,
        "transaction_reference" => $transactionRef,
        "new_balance" => (float)$newBalance['balance'],
        "message" => "Deposit successful"
    ]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => $e->getMessage(),
        "processed" => false
    ]);
}
