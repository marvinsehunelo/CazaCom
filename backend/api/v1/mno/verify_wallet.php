<?php
/**
 * /Backend/api/v1/mno/verify_wallet.php
 * CAZACOM - Verify wallet balance and ownership
 * Supports both API Key (for VouchMorph) and Session (for Web)
 */

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../security/ApiAuthenticator.php';

use Cazacom\Security\ApiAuthenticator;

error_log("=== CAZACOM verify_wallet.php CALLED ===");
error_log("Headers: " . json_encode(getallheaders()));

// ============================================
// 1. DATABASE CONNECTION
// ============================================
try {
    $pdo = new PDO(getenv('DATABASE_URL'));
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
// 2. CHECK AUTHENTICATION METHOD
// ============================================
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_X_APIKEY'] ?? null;
$isApiKeyRequest = !empty($apiKey);

error_log("API Key present: " . ($isApiKeyRequest ? 'YES (length: ' . strlen($apiKey) . ')' : 'NO'));

if ($isApiKeyRequest) {
    // ============================================
    // API KEY AUTHENTICATION (VouchMorph)
    // ============================================
    error_log("Authenticating with API Key: " . substr($apiKey, 0, 10) . '...');
    
    // Check environment variable first (for testing)
    $envApiKey = getenv('CAZACOM_API_KEY');
    if ($apiKey === $envApiKey) {
        error_log("API Key matched environment variable");
    } else {
        // Verify API key from database
        try {
            $stmt = $pdo->prepare("
                SELECT client_id, name, scopes, is_active 
                FROM oauth_clients 
                WHERE api_key = :api_key AND is_active = true
            ");
            $stmt->execute(['api_key' => $apiKey]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$client) {
                error_log("Invalid API Key - not found in database");
                http_response_code(401);
                echo json_encode([
                    "success" => false,
                    "verified" => false,
                    "message" => "Invalid API Key"
                ]);
                exit;
            }
            
            error_log("API Key authenticated: Client={$client['name']}");
            
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
        } catch (Exception $e) {
            error_log("API Key DB check failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "verified" => false,
                "message" => "Authentication service unavailable"
            ]);
            exit;
        }
    }
} else {
    // ============================================
    // SESSION AUTHENTICATION (Web Users)
    // ============================================
    error_log("Checking session authentication");
    session_start();
    
    // Check if user is logged in via session
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) {
        error_log("No session found");
        
        // Check if this is an API request (accepts JSON)
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (strpos($acceptHeader, 'application/json') !== false) {
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "verified" => false,
                "message" => "Authentication required. Please provide API Key or login."
            ]);
            exit;
        }
        
        // For web requests, redirect to login
        header('Location: login.php');
        exit();
    }
    
    error_log("Session authenticated: user_id=" . ($_SESSION['user_id'] ?? 'unknown'));
}

// ============================================
// 3. GET REQUEST DATA
// ============================================
$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    // Try to get from POST
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
$assetType = $input['asset_type'] ?? $input['destination_asset_type'] ?? 'MNO-WALLET';

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
// 5. GET WALLET BALANCE
// ============================================
try {
    // First check if user exists
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
            w.credit_limit,
            w.currency,
            w.status as wallet_status,
            w.pin_hash,
            w.pin_failed_attempts,
            w.pin_locked_until
        FROM users u
        LEFT JOIN wallets w ON u.id = w.user_id
        WHERE u.phone = :phone
        LIMIT 1
    ");
    $stmt->execute(['phone' => $searchPhone]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

    // If not found, try with formatted phone
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
                w.credit_limit,
                w.currency,
                w.status as wallet_status,
                w.pin_hash,
                w.pin_failed_attempts,
                w.pin_locked_until
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

    error_log("Found user: ID={$wallet['user_id']}, Name={$wallet['full_name']}, Wallet ID={$wallet['wallet_id']}");

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

    // Calculate available balance
    $balance = (float)($wallet['balance'] ?? 0);
    $creditBalance = (float)($wallet['credit_balance'] ?? 0);
    $creditLimit = (float)($wallet['credit_limit'] ?? 0);
    $availableBalance = $balance + $creditBalance + $creditLimit;

    error_log("Balance: $balance, Credit Balance: $creditBalance, Available: $availableBalance");

    // ============================================
    // 6. VERIFY PIN IF PROVIDED
    // ============================================
    if ($pin) {
        error_log("PIN provided, verifying...");
        
        // Check if PIN is locked
        if ($wallet['pin_locked_until'] && strtotime($wallet['pin_locked_until']) > time()) {
            $lockTime = date('Y-m-d H:i:s', strtotime($wallet['pin_locked_until']));
            error_log("PIN is locked until: $lockTime");
            echo json_encode([
                "success" => false,
                "verified" => false,
                "message" => "PIN is locked. Try again later.",
                "locked_until" => $lockTime
            ]);
            exit;
        }

        // Check if PIN hash exists
        if (empty($wallet['pin_hash'])) {
            error_log("No PIN set for user: {$wallet['user_id']}");
            echo json_encode([
                "success" => false,
                "verified" => false,
                "message" => "PIN not set for this wallet"
            ]);
            exit;
        }

        // Verify PIN
        if (!password_verify($pin, $wallet['pin_hash'])) {
            // Increment failed attempts
            $newAttempts = ($wallet['pin_failed_attempts'] ?? 0) + 1;
            $lockUntil = null;
            if ($newAttempts >= 3) {
                $lockUntil = date('Y-m-d H:i:s', time() + 900); // 15 minutes
            }
            
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
                "message" => "Invalid PIN",
                "attempts_remaining" => 3 - $newAttempts
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
        
        error_log("PIN verified successfully for user: {$wallet['user_id']}");
    }

    // ============================================
    // 7. CHECK SUFFICIENT BALANCE
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
            "credit_balance" => $creditBalance,
            "credit_limit" => $creditLimit
        ]
    ];

    error_log("Wallet verification successful: {$wallet['full_name']}, Balance: $availableBalance");
    echo json_encode($response);

} catch (PDOException $e) {
    error_log("PDO Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "verified" => false,
        "message" => "Database error occurred",
        "error" => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "verified" => false,
        "message" => "Internal error: " . $e->getMessage()
    ]);
}
