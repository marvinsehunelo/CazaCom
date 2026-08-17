<?php
// Cazacom: Place hold on wallet funds
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../security/ApiAuthenticator.php';
use Cazacom\Security\ApiAuthenticator;

// FIX: was `new PDO(getenv('DATABASE_URL'))` — not a valid PDO DSN.
// Single Database connection reused for both auth and the queries
// below, same as debit.php / credit.php.
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
$assetId = $input['asset_id'] ?? null;
$amount = (float)($input['amount'] ?? 0);
$expiry = $input['expiry'] ?? date('Y-m-d H:i:s', strtotime('+24 hours'));
$accessToken = $input['access_token'] ?? null;
if (!$reference || !$assetId || $amount <= 0) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}
$db->beginTransaction();
try {
    // Get user and wallet
    // FIX: was `SELECT id, phone_number FROM users` — column name is
    // correct here already (real schema uses phone_number, not phone),
    // left as-is.
    $stmt = $db->prepare("SELECT id, phone_number FROM users WHERE phone_number = :phone");
    $stmt->execute(['phone' => $assetId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found");
    }

    // Check current balance
    // NOTE: mobile_money_accounts has no `held_balance` column per the
    // real schema (id, user_id, balance, credit_balance, last_updated).
    // Selecting it here would throw the same "Undefined column" error
    // hit earlier on `full_name`/`wallets`. Removed from the SELECT;
    // this file already only reads `balance` from $wallet below, so
    // nothing downstream needed it.
    $stmt = $db->prepare("SELECT balance FROM mobile_money_accounts WHERE user_id = :user_id FOR UPDATE");
    $stmt->execute(['user_id' => $user['id']]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$wallet) {
        throw new Exception("No mobile money account found for this user");
    }

    $availableBalance = (float)$wallet['balance'];

    if ($availableBalance < $amount) {
        throw new Exception("Insufficient balance");
    }

    // Create hold
    $holdReference = 'HOLD-' . $reference . '-' . time();
    $stmt = $db->prepare("
        INSERT INTO financial_holds
        (hold_reference, user_id, amount, status, expires_at, created_at, source_reference)
        VALUES (:ref, :user_id, :amount, 'HELD', :expires, NOW(), :src_ref)
    ");
    $stmt->execute([
        'ref' => $holdReference,
        'user_id' => $user['id'],
        'amount' => $amount,
        'expires' => $expiry,
        'src_ref' => $reference
    ]);

    // Update wallet balance — funds are moved out of `balance` for the
    // duration of the hold. NOTE: since mobile_money_accounts has no
    // held_balance column, this file tracks the held amount only via
    // the financial_holds row itself, not via a running held_balance
    // total on the account. debit.php's commit step and any
    // release-hold flow should restore/consume funds consistently
    // with this same approach (see debit.php's matching note).
    $stmt = $db->prepare("
        UPDATE mobile_money_accounts
        SET balance = balance - :amount,
            last_updated = NOW()
        WHERE user_id = :user_id
    ");
    $stmt->execute(['amount' => $amount, 'user_id' => $user['id']]);

    $db->commit();

    echo json_encode([
        "status" => "success",
        "hold_placed" => true,
        "hold_reference" => $holdReference,
        "hold_expiry" => $expiry,
        "amount_held" => $amount,
        "available_balance" => $availableBalance - $amount
    ]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage(),
        "hold_placed" => false
    ]);
}
