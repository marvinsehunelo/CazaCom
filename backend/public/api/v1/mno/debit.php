<?php
// Cazacom: Final debit after successful transaction
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../security/ApiAuthenticator.php';
use Security\ApiAuthenticator;
// FIX: was `new PDO(getenv('DATABASE_URL'))` — DATABASE_URL is a
// connection URL (postgresql://user:pass@host/db), not a valid PDO
// DSN string, so this always threw before ApiAuthenticator ever ran.
// Use the Database class (same one config/db.php already defines and
// which the rest of this file already relies on below) once, and
// reuse the same connection for both auth and the actual queries
// instead of opening two separate connections.
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
// Same leftover-variable bug as hold.php/credit.php — $client was
// never defined in this version of the file. See hold.php's matching
// fix for the full explanation. requireAuth() in the current
// ApiAuthenticator returns only a participant name, no scopes list,
// so there is no real data here to check against. Disabled with a
// TODO rather than fabricated.
// ============================================================
// TODO: no scope enforcement currently possible — ApiAuthenticator::requireAuth()
// returns only a participant name, not a scopes list. Restore a real
// check here once scopes are modeled, or confirm scope enforcement is
// intentionally not required for this endpoint.

$input = json_decode(file_get_contents("php://input"), true);
$holdReference = $input['hold_reference'] ?? null;
$amount = (float)($input['amount'] ?? 0);
$destinationDetails = $input['destination_details'] ?? [];
if (!$holdReference) {
    // FIX: was missing 'success' — same fail-closed-default gap fixed
    // in hold.php/credit.php. Any response GenericBankClient can't
    // recognize now defaults to failure on VouchMorph's side, so this
    // MUST carry an explicit success signal to be read correctly
    // whether it succeeds or fails.
    echo json_encode(["success" => false, "status" => "error", "message" => "Hold reference required"]);
    exit;
}
$db->beginTransaction();
try {
    // Get hold details
    $stmt = $db->prepare("SELECT * FROM financial_holds WHERE hold_reference = :ref AND status = 'HELD' FOR UPDATE");
    $stmt->execute(['ref' => $holdReference]);
    $hold = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$hold) {
        throw new Exception("Hold not found");
    }
    // Update hold to committed
    $stmt = $db->prepare("
        UPDATE financial_holds
        SET status = 'COMMITTED',
            committed_at = NOW(),
            destination = :dest
        WHERE hold_reference = :ref
    ");
    $stmt->execute([
        'dest' => json_encode($destinationDetails),
        'ref' => $holdReference
    ]);
    // Update wallet held balance (funds are now permanently debited)
    // NOTE: mobile_money_accounts has no `held_balance` column per the
    // real schema (id, user_id, balance, credit_balance, last_updated).
    // hold.php below tracks holds by moving funds out of `balance`
    // directly rather than into a separate held_balance column, so on
    // commit there is nothing further to subtract from a held_balance
    // that doesn't exist — the debit already happened at hold time.
    // Left as a no-op update to `last_updated` only; if a real
    // held_balance column is added later, reintroduce the subtraction
    // here to match.
    $stmt = $db->prepare("
        UPDATE mobile_money_accounts
        SET last_updated = NOW()
        WHERE user_id = :user_id
    ");
    $stmt->execute(['user_id' => $hold['user_id']]);
    // Record transaction
    $transactionRef = 'TX-' . time() . '-' . bin2hex(random_bytes(8));
    // ============================================================
    // FIX: was inserting a 'destination' column that does not exist
    // on mobile_money_transactions. Real schema: id, user_id, type,
    // amount, fee, reference, recipient_phone, network, status,
    // wallet_type, created_at, completed_at — no destination column.
    // Every real debit through this file failed with SQLSTATE[42703]
    // AFTER the destination had already been credited (confirmed by
    // the "Debit failed after destination delivery succeeded" wrapper
    // message this produced upstream in SwapService) — meaning the
    // source hold was correctly left un-released and flagged for
    // manual reconciliation rather than double-paid, but the debit
    // itself never actually completed here.
    //
    // No data is lost by dropping this column reference: the full
    // $destinationDetails JSON is already persisted a few lines above,
    // in the `financial_holds.destination` UPDATE — this INSERT's copy
    // was redundant, not the only record of it.
    // ============================================================
    $stmt = $db->prepare("
        INSERT INTO mobile_money_transactions
        (user_id, type, amount, reference, status, completed_at)
        VALUES (:user_id, 'debit', :amount, :ref, 'completed', NOW())
    ");
    $stmt->execute([
        'user_id' => $hold['user_id'],
        'amount' => $hold['amount'],
        'ref' => $transactionRef
    ]);
    $db->commit();
    echo json_encode([
        // FIX: was missing a top-level 'success' boolean, same gap as
        // hold.php/credit.php had before their fixes — this file only
        // ever set 'status' (string) and 'debited' (bool). GenericBankClient::
        // send() checks 'success' first, then the action-specific
        // 'debited' flag, then falls back to 'status' === 'SUCCESS' as
        // a last resort; 'status' => 'success' (lowercase) here never
        // matched that literal string comparison either, so this
        // endpoint's genuinely successful debits were at real risk of
        // being misread as failures depending on which check path ran
        // first. Adding 'success' removes the ambiguity entirely.
        "success" => true,
        "status" => "success",
        "debited" => true,
        "transaction_reference" => $transactionRef,
        "amount" => (float)$hold['amount']
    ]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => $e->getMessage(),
        "debited" => false
    ]);
}
