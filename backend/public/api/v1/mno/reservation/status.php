<?php
// Cazacom: Get the status of a previously created reservation account.
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . '/../../../../../config/db.php';
require_once __DIR__ . '/../../../../../security/ApiAuthenticator.php';
use Security\ApiAuthenticator;

$database = new Database();
$db = $database->getConnection();
if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}
$auth = new ApiAuthenticator($db);
$auth->requireAuth();
// requireAuth() already sends a 401 and exit()s internally on failure —
// execution only reaches here with a valid, authenticated participant.

$input = json_decode(file_get_contents("php://input"), true);
$bankReference = $input['bank_reference'] ?? null;

if (!$bankReference) {
    echo json_encode(['success' => false, 'message' => 'bank_reference is required']);
    exit;
}

// Same defensive create as create.php, in case status is ever queried
// before this table has otherwise been provisioned.
$db->exec("
    CREATE TABLE IF NOT EXISTS cazacom_reservation_accounts (
        id BIGSERIAL PRIMARY KEY,
        bank_reference VARCHAR(150) UNIQUE NOT NULL,
        reference VARCHAR(150),
        user_id INTEGER,
        currency VARCHAR(10) DEFAULT 'BWP',
        account_identifier VARCHAR(100) NOT NULL,
        account_identifier_type VARCHAR(30) DEFAULT 'wallet_id',
        status VARCHAR(20) DEFAULT 'active',
        requester VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

try {
    $stmt = $db->prepare("SELECT * FROM cazacom_reservation_accounts WHERE bank_reference = :bank_reference");
    $stmt->execute(['bank_reference' => $bankReference]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        echo json_encode(['success' => false, 'message' => 'Reservation account not found for this bank_reference']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'status' => $account['status'],
        'account_identifier' => $account['account_identifier'],
        'account_identifier_type' => $account['account_identifier_type'],
        'message' => 'Reservation account status retrieved',
    ]);
} catch (PDOException $e) {
    error_log("[CAZACOM reservation/status.php] " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
