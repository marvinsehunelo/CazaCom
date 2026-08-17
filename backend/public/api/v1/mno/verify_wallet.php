<?php
/**
 * /Backend/api/v1/mno/verify_wallet.php
 * CAZACOM - Verify wallet balance and ownership
 * Simple API Key authentication for VouchMorph
 *
 * SCHEMA CORRECTED to match actual CAZACOM tables:
 *   users: id, name, phone_number, email, password_hash, pin_hash,
 *          created_at, pin_failed_attempts, pin_locked_until
 *          (no full_name, no phone, no kyc_verified)
 *   mobile_money_accounts: id, user_id, balance, credit_balance,
 *          last_updated
 *          (no wallets table, no currency, no status column)
 * PIN fields live on users, not on any wallet-side table — moved
 * accordingly.
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
$pdo = null;

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
            if (!isset($pdo) || $pdo === null) {
                // Matches the connection pattern used by hold.php / debit.php /
                // credit.php elsewhere in this codebase — NOT `new PDO(getenv(
                // 'DATABASE_URL'))` directly, which fails because DATABASE_URL
                // is a connection URL, not a valid PDO DSN string.
                $database = new Database();
                $pdo = $database->getConnection();
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
    if (!isset($pdo) || $pdo === null) {
        $database = new Database();
        $pdo = $database->getConnection();
    }
    if (!$pdo) {
        throw new Exception('getConnection() returned null');
    }
    error_log("Database connected successfully");
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "verified" => false,
        "message" => "Database connection failed",
        "details" => $e->getMessage()
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
         $input['source_identifier'] ??
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
    $stmt = $pdo->prepare("
        SELECT
            u.id as user_id,
            u.name as full_name,
            u.phone_number as user_phone,
            u.email,
            u.pin_hash,
            u.pin_failed_attempts,
            u.pin_locked_until,
            m.id as account_id,
            m.balance,
            m.credit_balance,
            m.last_updated
        FROM users u
        LEFT JOIN mobile_money_accounts m ON u.id = m.user_id
        WHERE u.phone_number = :phone
        LIMIT 1
    ");
    $stmt->execute(['phone' => $searchPhone]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

    // Try with formatted phone if not found
    if (!$wallet) {
        $stmt = $pdo->prepare("
            SELECT
                u.id as user_id,
                u.name as full_name,
                u.phone_number as user_phone,
                u.email,
                u.pin_hash,
                u.pin_failed_attempts,
                u.pin_locked_until,
                m.id as account_id,
                m.balance,
                m.credit_balance,
                m.last_updated
            FROM users u
            LEFT JOIN mobile_money_accounts m ON u.id = m.user_id
            WHERE u.phone_number = :phone
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

    // Check if a mobile money account exists for this user
    if (empty($wallet['account_id'])) {
        error_log("No mobile money account found for user: {$wallet['user_id']}");
        echo json_encode([
            "success" => false,
            "verified" => false,
            "message" => "No wallet found for this user"
        ]);
        exit;
    }

    // No status column exists on mobile_money_accounts — an account
    // row existing at all is treated as active. If CAZACOM adds an
    // account-status concept later, reintroduce a check here.

    // Calculate balance
    $balance = (float)($wallet['balance'] ?? 0);
    $creditBalance = (float)($wallet['credit_balance'] ?? 0);
    $availableBalance = $balance + $creditBalance;

    error_log("Balance: $balance, Credit Balance: $creditBalance, Available: $availableBalance");

    // ============================================
    // 6. VERIFY PIN (Optional)
    //
    // PIN fields live on `users`, not on any wallet-side table — the
    // original version queried a non-existent `wallets` table by
    // wallet_id for this. Corrected to use the PIN fields already
    // fetched above for this user.
    // ============================================
    if ($pin) {
        error_log("PIN verification requested");

        if (!empty($wallet['pin_hash'])) {
            // Check if PIN is locked
            if ($wallet['pin_locked_until'] && strtotime($wallet['pin_locked_until']) > time()) {
                echo json_encode([
                    "success" => false,
                    "verified" => false,
                    "message" => "PIN is locked. Try again later."
                ]);
                exit;
            }

            // Verify PIN
            if (!password_verify($pin, $wallet['pin_hash'])) {
                // Increment failed attempts
                $newAttempts = ($wallet['pin_failed_attempts'] ?? 0) + 1;
                $lockUntil = $newAttempts >= 3 ? date('Y-m-d H:i:s', time() + 900) : null;

                $updateStmt = $pdo->prepare("
                    UPDATE users
                    SET pin_failed_attempts = :attempts,
                        pin_locked_until = :lock_until
                    WHERE id = :user_id
                ");
                $updateStmt->execute([
                    'attempts' => $newAttempts,
                    'lock_until' => $lockUntil,
                    'user_id' => $wallet['user_id']
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
                UPDATE users
                SET pin_failed_attempts = 0,
                    pin_locked_until = NULL
                WHERE id = :user_id
            ");
            $resetStmt->execute(['user_id' => $wallet['user_id']]);

            error_log("PIN verified successfully");
        } else {
            error_log("No PIN set for user");
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
    //
    // currency defaults to BWP since mobile_money_accounts has no
    // currency column — matches every other participant in this
    // deployment, which are all BWP-only per participants.yaml.
    // kyc_verified has no backing column, defaults to false rather
    // than silently claiming verification that was never checked.
    // ============================================
    $response = [
        "success" => true,
        "verified" => true,
        "asset_id" => $wallet['account_id'],
        "asset_type" => "MNO-WALLET",
        "balance" => $availableBalance,
        "available_balance" => $availableBalance,
        "currency" => 'BWP',
        "owner_name" => $wallet['full_name'] ?? 'Unknown User',
        "phone_number" => $formattedPhone,
        "email" => $wallet['email'] ?? null,
        "kyc_verified" => false,
        "metadata" => [
            "user_id" => $wallet['user_id'],
            "account_id" => $wallet['account_id'],
            "balance" => $balance,
            "credit_balance" => $creditBalance,
            "last_updated" => $wallet['last_updated'] ?? null
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
