<?php
// Cazacom: Credit funds to wallet (for incoming swaps)
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../security/ApiAuthenticator.php';
use Cazacom\Security\ApiAuthenticator;

// FIX: was `new PDO(getenv('DATABASE_URL'))` — not a valid PDO DSN.
// Single Database connection reused for both auth and the queries
// below, same as hold.php / debit.php.
$database = new Database();
$db = $database->getConnection();
if (!$db) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$auth = new ApiAuthenticator($db);
$client = $auth->authenticate();
if (!in_array('initiate_payment', $client['scopes'])) {
    http_response_code(403);
    echo json_encode(['error' => 'insufficient_scope', 'message' => 'initiate_payment scope required']);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$reference = $input['reference'] ?? null;
$destinationId = $input['destination_id'] ?? null;
$amount = (float)($input['amount'] ?? 0);
$sourceHoldReference = $input['source_hold_reference'] ?? null;
if (!$reference || !$destinationId || $amount <= 0) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
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
    $stmt = $db->prepare("
        INSERT INTO mobile_money_transactions
        (user_id, type, amount, reference, status, completed_at, source_reference)
        VALUES (:user_id, 'credit', :amount, :ref, 'completed', NOW(), :src)
    ");
    $stmt->execute([
        'user_id' => $user['id'],
        'amount' => $amount,
        'ref' => $transactionRef,
        'src' => $sourceHoldReference
    ]);

    $db->commit();

    // Get new balance
    $stmt = $db->prepare("SELECT balance FROM mobile_money_accounts WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user['id']]);
    $newBalance = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "processed" => true,
        "transaction_reference" => $transactionRef,
        "new_balance" => (float)$newBalance['balance']
    ]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage(),
        "processed" => false
    ]);
}
