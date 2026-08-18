<?php
// Cazacom: Release a previously placed hold (funds return to balance)
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../security/ApiAuthenticator.php';
use Security\ApiAuthenticator;

// Same connection pattern as hold.php / debit.php / credit.php —
// new PDO(getenv('DATABASE_URL')) is not a valid DSN, so it's not
// used here.
$database = new Database();
$db = $database->getConnection();
if (!$db) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$auth = new ApiAuthenticator($db);
$participant = $auth->requireAuth(); 
if (!in_array('initiate_payment', $client['scopes'])) {
    http_response_code(403);
    echo json_encode(['error' => 'insufficient_scope', 'message' => 'initiate_payment scope required']);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$holdReference = $input['hold_reference'] ?? null;
$reason = $input['reason'] ?? 'Released';

if (!$holdReference) {
    echo json_encode(["status" => "error", "message" => "Hold reference required"]);
    exit;
}

$db->beginTransaction();
try {
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
        // Not found, or found but not in HELD status (already
        // committed/released) — either way, nothing to release.
        throw new Exception("Hold not found or not in a releasable state");
    }

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

    echo json_encode([
        "status" => "success",
        "released" => true,
        "hold_reference" => $holdReference,
        "amount_released" => (float)$hold['amount'],
        "reason" => $reason
    ]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage(),
        "released" => false
    ]);
}
