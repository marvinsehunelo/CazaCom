<?php
// Cazacom: Verify wallet balance and ownership

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../security/ApiAuthenticator.php';

use Cazacom\Security\ApiAuthenticator;

// ============================================
// EUROPEAN BANKING GRADE AUTHENTICATION
// ============================================
$pdo = new PDO(getenv('DATABASE_URL'));
$auth = new ApiAuthenticator($pdo);
$client = $auth->authenticate();

// Check scope
if (!in_array('read_balance', $client['scopes'])) {
    http_response_code(403);
    echo json_encode(['error' => 'insufficient_scope', 'message' => 'read_balance scope required']);
    exit;
}
 
$database = new Database();
$db = $database->getConnection();

// Get request data
$input = json_decode(file_get_contents("php://input"), true);
$reference = $input['reference'] ?? null;
$assetType = $input['asset_type'] ?? 'MNO-WALLET';
$amount = (float)($input['amount'] ?? 0);
$credentials = $input['credentials'] ?? [];
$accessToken = $input['access_token'] ?? null;

if (!$reference) {
    echo json_encode(["status" => "error", "message" => "Reference required"]);
    exit;
}

// Get phone number from credentials or token
$phone = $credentials['phone'] ?? null;

if (!$phone && $accessToken) {
    // Validate access token and get user
    $stmt = $db->prepare("SELECT user_id FROM oauth_access_tokens WHERE token = :token AND expires_at > NOW()");
    $stmt->execute(['token' => $accessToken]);
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($tokenData) {
        $stmt = $db->prepare("SELECT phone_number FROM users WHERE id = :user_id");
        $stmt->execute(['user_id' => $tokenData['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $phone = $user['phone_number'] ?? null;
    }
}

if (!$phone) {
    echo json_encode(["status" => "error", "message" => "Phone number required"]);
    exit;
}

// Get wallet balance
$stmt = $db->prepare("SELECT balance, credit_balance FROM mobile_money_accounts WHERE user_id = (SELECT id FROM users WHERE phone_number = :phone)");
$stmt->execute(['phone' => $phone]);
$wallet = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$wallet) {
    echo json_encode([
        "status" => "error",
        "message" => "Wallet not found",
        "verified" => false
    ]);
    exit;
}

$availableBalance = (float)($wallet['balance'] + $wallet['credit_balance']);

if ($amount > 0 && $availableBalance < $amount) {
    echo json_encode([
        "status" => "error",
        "message" => "Insufficient balance",
        "verified" => false,
        "available_balance" => $availableBalance,
        "requested_amount" => $amount
    ]);
    exit;
}

echo json_encode([
    "status" => "success",
    "verified" => true,
    "asset_id" => $phone,
    "asset_type" => "MNO-WALLET",
    "balance" => $availableBalance,
    "available_balance" => $availableBalance,
    "currency" => "BWP",
    "owner_name" => $this->getUserName($db, $phone)
]);
