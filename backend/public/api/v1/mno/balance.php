<?php
/**
 * /Backend/api/v1/mno/balance.php
 * CAZACOM - Get wallet balance
 * Simple API Key authentication for VouchMorph
 *
 * Adapted from verify_wallet.php's working auth + wallet lookup logic.
 * This endpoint is balance-only: no PIN check, no amount/insufficient-
 * balance check (that belongs to the caller's own contribution logic
 * downstream, not this lookup).
 */

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . '/../../../../config/db.php';

error_log("=== CAZACOM balance.php CALLED ===");
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
                // NOTE: was `new PDO(getenv('DATABASE_URL'))` — DATABASE_URL
                // on Railway is a connection URL (postgresql://user:pass@host/db),
                // not a valid PDO DSN string, so that call always threw.
                // getDB() (defined in config/db.php) correctly parses the URL
                // into a proper pgsql: DSN before connecting.
                $pdo = getDB();
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
        "message" => "Invalid or missing API Key. Please provide a valid X-API-Key header."
    ]);
    exit;
}

// ============================================
// 2. DATABASE CONNECTION
// ============================================
try {
    if (!isset($pdo)) {
        $pdo = getDB();
    }
    error_log("Database connected successfully");
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
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

// NOTE: 'source_identifier' is included here because that's the actual
// field name GenericBankClient sends for get_balance calls (confirmed
// from VouchMorph logs) — it was missing from this list in the original
// verify_wallet.php, which is a likely reason a balance lookup by phone
// could silently miss even when other fields would have matched.
$phone = $input['phone'] ??
         $input['wallet_phone'] ??
         $input['destination_phone'] ??
         $input['account_identifier'] ??
         $input['identifier'] ??
         $input['destination_phone_number'] ??
         $input['source_identifier'] ??
         null;

$reference = $input['reference'] ?? null;

error_log("Phone: $phone, Reference: $reference");

if (!$phone) {
    error_log("No phone number provided");
    http_response_code(400);
    echo json_encode([
        "success" => false,
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
    // NOTE: schema corrected to match actual CAZACOM tables —
    // `users` has `name`/`phone_number` (not `full_name`/`phone`,
    // and no `kyc_verified` column), and the wallet table is
    // `mobile_money_accounts` (not `wallets`), which has no
    // `currency` or `status` column.
    $stmt = $pdo->prepare("
        SELECT
            u.id as user_id,
            u.name as full_name,
            u.phone_number as user_phone,
            u.email,
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
            "message" => "Wallet not found",
            "debug" => [
                "phone_searched" => $searchPhone,
                "formatted_phone" => $formattedPhone
            ]
        ]);
        exit;
    }

    error_log("Found user: ID={$wallet['user_id']}, Name={$wallet['full_name']}");

    if (empty($wallet['account_id'])) {
        error_log("No mobile money account found for user: {$wallet['user_id']}");
        echo json_encode([
            "success" => false,
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
    // 6. SUCCESS RESPONSE
    //
    // Shape matches what GenericBankClient::send() reads for get_balance:
    // it looks at $result['data']['balance'] ?? $result['data']['available_balance'],
    // so both keys are provided under 'data' — same nesting SACCUSSALIS
    // and ZURUBANK's real balance.php endpoints already use, and which
    // the [GenericBankClient] "Balance from nested data" log line expects.
    //
    // currency defaults to BWP since mobile_money_accounts has no
    // currency column — matches every other participant in this
    // deployment, which are all BWP-only per participants.yaml.
    // ============================================
    $response = [
        "status" => "success",
        "verified" => true,
        "data" => [
            "account_id" => $wallet['account_id'],
            "user_id" => $wallet['user_id'],
            "phone_number" => $formattedPhone,
            "asset_type" => "WALLET",
            "balance" => $availableBalance,
            "available_balance" => $availableBalance,
            "currency" => 'BWP',
            "last_updated" => $wallet['last_updated'] ?? null,
            "timestamp" => time()
        ],
        "requester" => "CAZACOM",
        "verification_method" => "database"
    ];

    error_log("Balance lookup successful: {$wallet['full_name']}, Balance: $availableBalance");
    echo json_encode($response);

} catch (PDOException $e) {
    error_log("PDO Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Internal error: " . $e->getMessage()
    ]);
}
