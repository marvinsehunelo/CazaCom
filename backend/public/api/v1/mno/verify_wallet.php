<?php
/**
 * /Backend/api/v1/mno/verify_wallet.php
 * CAZACOM - Verify wallet balance and ownership
 * Simple API Key authentication for VouchMorph
 */

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . '/../../../../config/db.php';

error_log("=== CAZACOM verify_wallet.php CALLED ===");
error_log("Headers: " . json_encode(getallheaders()));
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);

// ============================================
// 1. CHECK API KEY (Simple & Direct)
// ============================================
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_X_APIKEY'] ?? null;
error_log("API Key received: " . ($apiKey ? substr($apiKey, 0, 20) . '...' : 'NONE'));

// Define valid API keys (hardcoded for reliability)
$validApiKeys = [
    'vouchmorph_live_1aB2cD3eF4gH5iJ6' => 'VOUCHMORPH',
    'cazacom_internal_key' => 'CAZACOM_INTERNAL',
    'test_api_key_123' => 'TEST'
];

// Check environment variable as well
$envApiKey = getenv('CAZACOM_API_KEY');

// Validate API Key
$isValidApiKey = false;
$clientName = 'Unknown';

if ($apiKey) {
    // Check hardcoded keys
    if (isset($validApiKeys[$apiKey])) {
        $isValidApiKey = true;
        $clientName = $validApiKeys[$apiKey];
        error_log("API Key validated from hardcoded list: $clientName");
    }
    // Check environment variable
    elseif ($envApiKey && $apiKey === $envApiKey) {
        $isValidApiKey = true;
        $clientName = 'ENV_CLIENT';
        error_log("API Key validated from environment variable");
    }
    // Check database
    else {
        try {
            if (!isset($pdo)) {
                $pdo = new PDO(getenv('DATABASE_URL'));
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
            
            $stmt = $pdo->prepare("
                SELECT client_name, is_active 
                FROM api_clients 
                WHERE api_key = :api_key AND is_active = true
            ");
            $stmt->execute(['api_key' => $apiKey]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($client) {
                $isValidApiKey = true;
                $clientName = $client['client_name'];
                error_log("API Key validated from database: $clientName");
            }
        } catch (Exception $e) {
            error_log("Database API key check failed: " . $e->getMessage());
        }
    }
}

// If API key is invalid, return 401 (don't redirect)
if (!$isValidApiKey) {
    error_log("Invalid or missing API Key");
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "verified" => false,
        "message" => "Invalid or missing API Key. Please provide a valid X-API-Key header."
    ]);
    exit;
}

// ============================================
// 2. DATABASE CONNECTION
// ============================================
try {
    if (!isset($pdo)) {
        $pdo = new PDO(getenv('DATABASE_URL'));
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    error_log("Database connected successfully");
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "verified" => false,
        "message" => "Database connection failed"
    ]);
    exit;
}

// ============================================
// 3. GET REQUEST DATA
// ============================================
$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    $input = $_POST;
}
error_log("Input data: " . json_encode($input));

$phone = $input['phone'] ?? 
         $input['wallet_phone'] ?? 
         $input['destination_phone'] ?? 
         $input['account_identifier'] ?? 
         $input['identifier'] ?? 
         $input['destination_phone_number'] ?? 
         null;

$amount = isset($input['amount']) ? (float)$input['amount'] : 0;
$reference = $input['reference'] ?? null;
$pin = $input['pin'] ?? $input['wallet_pin'] ?? null;

error_log("Phone: $phone, Amount: $amount, Pin: " . ($pin ? 'provided' : 'not provided'));

if (!$phone) {
    error_log("No phone number provided");
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "verified" => false,
        "message" => "Phone number required"
    ]);
    exit;
}

// ============================================
// 4. FORMAT PHONE NUMBER
// ============================================
$cleanPhone = preg_replace('/[^0-9]/', '', $phone);
if (strpos($cleanPhone, '267') === 0) {
    $formattedPhone = '+' . $cleanPhone;
} else {
    $formattedPhone = '+267' . $cleanPhone;
}
$searchPhone = ltrim($formattedPhone, '+');

error_log("Searching for wallet with phone: $searchPhone (formatted: $formattedPhone)");

// ============================================
// 5. GET WALLET DATA
// ============================================
try {
    // First check users table
    $stmt = $pdo->prepare("
        SELECT 
            u.id as user_id,
            u.full_name,
            u.phone as user_phone,
            u.email,
            u.kyc_verified,
            w.id as wallet_id,
            w.balance,
            w.credit_balance,
            w.currency,
            w.status as wallet_status
        FROM users u
        LEFT JOIN wallets w ON u.id = w.user_id
        WHERE u.phone = :phone
        LIMIT 1
    ");
    $stmt->execute(['phone' => $searchPhone]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

    // Try with formatted phone if not found
    if (!$wallet) {
        $stmt = $pdo->prepare("
            SELECT 
                u.id as user_id,
                u.full_name,
                u.phone as user_phone,
                u.email,
                u.kyc_verified,
                w.id as wallet_id,
                w.balance,
                w.credit_balance,
                w.currency,
                w.status as wallet_status
            FROM users u
            LEFT JOIN wallets w ON u.id = w.user_id
            WHERE u.phone = :phone
            LIMIT 1
        ");
        $stmt->execute(['phone' => $formattedPhone]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
    }

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

    error_log("Found user: ID={$wallet['user_id']}, Name={$wallet['full_name']}");

    // Check if wallet exists
    if (empty($wallet['wallet_id'])) {
        error_log("No wallet found for user: {$wallet['user_id']}");
        echo json_encode([
            "success" => false,
            "verified" => false,
            "message" => "No wallet found for this user"
        ]);
        exit;
    }

    // Check wallet status
    if ($wallet['wallet_status'] !== 'active') {
        error_log("Wallet not active: {$wallet['wallet_status']}");
        echo json_encode([
            "success" => false,
            "verified" => false,
            "message" => "Wallet is not active (status: {$wallet['wallet_status']})"
        ]);
        exit;
    }

    // Calculate balance
    $balance = (float)($wallet['balance'] ?? 0);
    $creditBalance = (float)($wallet['credit_balance'] ?? 0);
    $availableBalance = $balance + $creditBalance;

    error_log("Balance: $balance, Credit Balance: $creditBalance, Available: $availableBalance");

    // ============================================
    // 6. VERIFY PIN (Optional)
    // ============================================
    if ($pin) {
        error_log("PIN verification requested");
        // Get PIN hash from wallet
        $pinStmt = $pdo->prepare("
            SELECT pin_hash, pin_failed_attempts, pin_locked_until
            FROM wallets
            WHERE id = :wallet_id
        ");
        $pinStmt->execute(['wallet_id' => $wallet['wallet_id']]);
        $pinData = $pinStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pinData && !empty($pinData['pin_hash'])) {
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
                $lockUntil = $newAttempts >= 3 ? date('Y-m-d H:i:s', time() + 900) : null;
                
                $updateStmt = $pdo->prepare("
                    UPDATE wallets 
                    SET pin_failed_attempts = :attempts,
                        pin_locked_until = :lock_until
                    WHERE id = :wallet_id
                ");
                $updateStmt->execute([
                    'attempts' => $newAttempts,
                    'lock_until' => $lockUntil,
                    'wallet_id' => $wallet['wallet_id']
                ]);
                
                error_log("Invalid PIN for user {$wallet['user_id']}. Attempts: $newAttempts");
                echo json_encode([
                    "success" => false,
                    "verified" => false,
                    "message" => "Invalid PIN"
                ]);
                exit;
            }
            
            // Reset failed attempts on success
            $resetStmt = $pdo->prepare("
                UPDATE wallets 
                SET pin_failed_attempts = 0,
                    pin_locked_until = NULL
                WHERE id = :wallet_id
            ");
            $resetStmt->execute(['wallet_id' => $wallet['wallet_id']]);
            
            error_log("PIN verified successfully");
        } else {
            error_log("No PIN set for wallet");
            // Continue without PIN verification if no PIN set
        }
    }

    // ============================================
    // 7. CHECK BALANCE
    // ============================================
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
    // 8. SUCCESS RESPONSE
    // ============================================
    $response = [
        "success" => true,
        "verified" => true,
        "asset_id" => $wallet['wallet_id'],
        "asset_type" => "MNO-WALLET",
        "balance" => $availableBalance,
        "available_balance" => $availableBalance,
        "currency" => $wallet['currency'] ?? 'BWP',
        "owner_name" => $wallet['full_name'] ?? 'Unknown User',
        "phone_number" => $formattedPhone,
        "email" => $wallet['email'] ?? null,
        "kyc_verified" => (bool)($wallet['kyc_verified'] ?? false),
        "metadata" => [
            "user_id" => $wallet['user_id'],
            "wallet_id" => $wallet['wallet_id'],
            "wallet_status" => $wallet['wallet_status'],
            "balance" => $balance,
            "credit_balance" => $creditBalance
        ]
    ];

    error_log("Wallet verification successful: {$wallet['full_name']}, Balance: $availableBalance");
    echo json_encode($response);

} catch (PDOException $e) {
    error_log("PDO Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "verified" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "verified" => false,
        "message" => "Internal error: " . $e->getMessage()
    ]);
}
