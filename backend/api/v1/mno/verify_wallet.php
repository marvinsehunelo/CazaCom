<?php
/**
 * /Backend/api/v1/mno/verify_wallet.php
 * CAZACOM - Verify wallet balance and ownership
 * Supports both OAuth (for users) and API Key (for VouchMorph)
 */

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../security/ApiAuthenticator.php';

use Cazacom\Security\ApiAuthenticator;

// ============================================
// SUPPORT BOTH OAUTH AND API KEY AUTH
// ============================================
$pdo = new PDO(getenv('DATABASE_URL'));

// Check if this is an API key request (from VouchMorph)
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_X_APIKEY'] ?? null;
$isApiKeyRequest = !empty($apiKey);

error_log("=== CAZACOM verify_wallet called ===");
error_log("API Key present: " . ($isApiKeyRequest ? 'YES' : 'NO'));
error_log("Headers: " . json_encode(getallheaders()));

if ($isApiKeyRequest) {
    // ============================================
    // API KEY AUTHENTICATION (VouchMorph)
    // ============================================
    error_log("Authenticating with API Key: " . substr($apiKey, 0, 10) . '...');
    
    // Verify API key from database
    $stmt = $pdo->prepare("
        SELECT client_id, name, scopes, is_active 
        FROM oauth_clients 
        WHERE api_key = :api_key AND is_active = true
    ");
    $stmt->execute(['api_key' => $apiKey]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        error_log("Invalid API Key");
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "verified" => false,
            "message" => "Invalid API Key"
        ]);
        exit;
    }
    
    error_log("API Key authenticated: Client={$client['name']}, Scopes={$client['scopes']}");
    
    // Check scope
    $scopes = is_array($client['scopes']) ? $client['scopes'] : json_decode($client['scopes'] ?? '[]', true);
    if (!in_array('read_balance', $scopes) && !in_array('*', $scopes)) {
        error_log("Insufficient scope: read_balance required");
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "verified" => false,
            "message" => "Insufficient scope: read_balance required"
        ]);
        exit;
    }
} else {
    // ============================================
    // OAUTH AUTHENTICATION (Regular users)
    // ============================================
    error_log("Authenticating with OAuth");
    $auth = new ApiAuthenticator($pdo);
    $client = $auth->authenticate();
    
    // Check scope
    if (!in_array('read_balance', $client['scopes'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'verified' => false,
            'error' => 'insufficient_scope',
            'message' => 'read_balance scope required'
        ]);
        exit;
    }
}

// ============================================
// GET DATABASE CONNECTION
// ============================================
$database = new Database();
$db = $database->getConnection();

// ============================================
// GET REQUEST DATA
// ============================================
$input = json_decode(file_get_contents("php://input"), true);
error_log("Input data: " . json_encode($input));

$reference = $input['reference'] ?? null;
$assetType = $input['asset_type'] ?? $input['destination_asset_type'] ?? 'MNO-WALLET';
$amount = (float)($input['amount'] ?? 0);
$credentials = $input['credentials'] ?? [];
$accessToken = $input['access_token'] ?? null;

// Get phone number - check multiple sources
$phone = $credentials['phone'] ?? 
         $input['phone'] ?? 
         $input['wallet_phone'] ?? 
         $input['destination_phone'] ?? 
         $input['account_identifier'] ?? 
         $input['identifier'] ?? 
         null;

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
    error_log("No phone number provided");
    echo json_encode([
        "success" => false,
        "verified" => false,
        "message" => "Phone number required"
    ]);
    exit;
}

// Format phone number
$cleanPhone = preg_replace('/[^0-9]/', '', $phone);
if (strpos($cleanPhone, '267') === 0) {
    $formattedPhone = '+' . $cleanPhone;
} else {
    $formattedPhone = '+267' . $cleanPhone;
}
$searchPhone = ltrim($formattedPhone, '+');

error_log("Looking for wallet with phone: $formattedPhone (search: $searchPhone)");

// ============================================
// GET WALLET BALANCE
// ============================================
$stmt = $db->prepare("
    SELECT 
        u.id,
        u.name,
        u.phone_number,
        mma.balance,
        mma.credit_balance,
        mma.account_status,
        mma.currency
    FROM users u
    LEFT JOIN mobile_money_accounts mma ON u.id = mma.user_id
    WHERE u.phone_number = :phone
    LIMIT 1
");
$stmt->execute(['phone' => $searchPhone]);
$wallet = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$wallet) {
    error_log("Wallet not found for phone: $searchPhone");
    echo json_encode([
        "success" => false,
        "verified" => false,
        "message" => "Wallet not found",
        "debug" => [
            "phone_searched" => $searchPhone,
            "formatted_phone" => $formattedPhone
        ]
    ]);
    exit;
}

error_log("Found wallet: User={$wallet['name']}, Balance={$wallet['balance']}");

// Check account status
if ($wallet['account_status'] !== 'active') {
    error_log("Wallet not active: {$wallet['account_status']}");
    echo json_encode([
        "success" => false,
        "verified" => false,
        "message" => "Wallet is not active (status: {$wallet['account_status']})"
    ]);
    exit;
}

$availableBalance = (float)($wallet['balance'] + $wallet['credit_balance']);

// Check if PIN is required
$pin = $input['pin'] ?? $input['wallet_pin'] ?? null;
if ($pin) {
    // Verify PIN
    $stmt = $db->prepare("
        SELECT pin_hash, pin_failed_attempts, pin_locked_until
        FROM mobile_money_accounts
        WHERE user_id = :user_id
    ");
    $stmt->execute(['user_id' => $wallet['id']]);
    $pinData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pinData) {
        // Check if PIN is locked
        if ($pinData['pin_locked_until'] && strtotime($pinData['pin_locked_until']) > time()) {
            echo json_encode([
                "success" => false,
                "verified" => false,
                "message" => "PIN is locked. Try again later."
            ]);
            exit;
        }
        
        // Verify PIN
        if (!password_verify($pin, $pinData['pin_hash'])) {
            // Increment failed attempts
            $newAttempts = ($pinData['pin_failed_attempts'] ?? 0) + 1;
            $lockUntil = null;
            if ($newAttempts >= 3) {
                $lockUntil = date('Y-m-d H:i:s', time() + 900);
            }
            
            $updateStmt = $db->prepare("
                UPDATE mobile_money_accounts 
                SET pin_failed_attempts = :attempts,
                    pin_locked_until = :lock_until
                WHERE user_id = :user_id
            ");
            $updateStmt->execute([
                'attempts' => $newAttempts,
                'lock_until' => $lockUntil,
                'user_id' => $wallet['id']
            ]);
            
            error_log("Invalid PIN for user {$wallet['id']}. Attempts: $newAttempts");
            echo json_encode([
                "success" => false,
                "verified" => false,
                "message" => "Invalid PIN"
            ]);
            exit;
        }
        
        // Reset failed attempts on successful PIN
        $resetStmt = $db->prepare("
            UPDATE mobile_money_accounts 
            SET pin_failed_attempts = 0,
                pin_locked_until = NULL
            WHERE user_id = :user_id
        ");
        $resetStmt->execute(['user_id' => $wallet['id']]);
    }
}

// Check if sufficient balance
if ($amount > 0 && $availableBalance < $amount) {
    error_log("Insufficient balance: Available $availableBalance, Requested $amount");
    echo json_encode([
        "success" => false,
        "verified" => false,
        "message" => "Insufficient balance",
        "available_balance" => $availableBalance,
        "requested_amount" => $amount
    ]);
    exit;
}

// ============================================
// GET USER NAME FUNCTION
// ============================================
function getUserName($db, $phone) {
    $stmt = $db->prepare("SELECT name FROM users WHERE phone_number = :phone");
    $stmt->execute(['phone' => $phone]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user['name'] ?? 'Unknown User';
}

// ============================================
// SUCCESS RESPONSE
// ============================================
$response = [
    "success" => true,
    "verified" => true,
    "asset_id" => $wallet['id'],
    "asset_type" => "MNO-WALLET",
    "balance" => $availableBalance,
    "available_balance" => $availableBalance,
    "currency" => $wallet['currency'] ?? 'BWP',
    "owner_name" => $wallet['name'] ?? getUserName($db, $searchPhone),
    "phone_number" => $formattedPhone,
    "metadata" => [
        "user_id" => $wallet['id'],
        "account_status" => $wallet['account_status'],
        "balance" => $wallet['balance'],
        "credit_balance" => $wallet['credit_balance'] ?? 0
    ]
];

error_log("Wallet verification successful: User={$wallet['name']}, Balance=$availableBalance");

echo json_encode($response);
