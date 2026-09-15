<?php
// Cazacom: Create a reservation account — a dedicated wallet opened for a
// beneficiary who hasn't linked a real account yet, replacing the old
// shared pooled wallet for unclaimed funds.
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . '/../../../../../config/db.php';
require_once __DIR__ . '/../../../../../security/ApiAuthenticator.php';
use Security\ApiAuthenticator;

// Same connection + auth pattern as hold.php / credit.php.
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

$bankReference = $input['bank_reference'] ?? $input['reference'] ?? null;
$reference = $input['reference'] ?? $bankReference;
$userId = $input['user_id'] ?? null;
$currency = $input['currency'] ?? 'BWP';
$requester = $input['requester'] ?? null;

if (!$bankReference) {
    echo json_encode(['success' => false, 'message' => 'bank_reference is required']);
    exit;
}

// cazacom_reservation_accounts isn't part of the migrated schema yet —
// created defensively here so this endpoint works without a separate
// out-of-band migration step.
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

function respondExisting(array $existing): void
{
    echo json_encode([
        'success' => true,
        'status' => $existing['status'],
        'account_identifier' => $existing['account_identifier'],
        'account_identifier_type' => $existing['account_identifier_type'],
        'message' => 'Reservation account already exists for this bank_reference',
    ]);
    exit;
}

try {
    // Idempotency: VouchMorph retries with the SAME bank_reference after a
    // timeout or ambiguous response — look it up first and return the
    // existing account instead of creating a second one.
    $stmt = $db->prepare("SELECT * FROM cazacom_reservation_accounts WHERE bank_reference = :bank_reference");
    $stmt->execute(['bank_reference' => $bankReference]);
    if ($existing = $stmt->fetch(PDO::FETCH_ASSOC)) {
        respondExisting($existing);
    }

    $accountIdentifier = 'CZRA' . strtoupper(bin2hex(random_bytes(6)));

    $stmt = $db->prepare("
        INSERT INTO cazacom_reservation_accounts
        (bank_reference, reference, user_id, currency, account_identifier, account_identifier_type, status, requester)
        VALUES (:bank_reference, :reference, :user_id, :currency, :account_identifier, 'wallet_id', 'active', :requester)
    ");
    $stmt->execute([
        'bank_reference' => $bankReference,
        'reference' => $reference,
        'user_id' => $userId,
        'currency' => $currency,
        'account_identifier' => $accountIdentifier,
        'requester' => $requester,
    ]);

    echo json_encode([
        'success' => true,
        'status' => 'active',
        'account_identifier' => $accountIdentifier,
        'account_identifier_type' => 'wallet_id',
        'message' => 'Reservation account created',
    ]);
} catch (PDOException $e) {
    // Unique violation on bank_reference means a concurrent retry won the
    // race to create it first — return that one instead of erroring.
    if ($e->getCode() === '23505') {
        $stmt = $db->prepare("SELECT * FROM cazacom_reservation_accounts WHERE bank_reference = :bank_reference");
        $stmt->execute(['bank_reference' => $bankReference]);
        if ($existing = $stmt->fetch(PDO::FETCH_ASSOC)) {
            respondExisting($existing);
        }
    }
    error_log("[CAZACOM reservation/create.php] " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
