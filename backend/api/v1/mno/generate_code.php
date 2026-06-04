<?php
// Cazacom: Generate ATM/Agent cashout code

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . '/../../config/db.php';

$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents("php://input"), true);
$reference = $input['reference'] ?? null;
$beneficiaryPhone = $input['beneficiary_phone'] ?? null;
$amount = (float)($input['amount'] ?? 0);
$codeHash = $input['code_hash'] ?? null;
$expiry = $input['expiry'] ?? date('Y-m-d H:i:s', strtotime('+24 hours'));

if (!$reference || !$beneficiaryPhone || $amount <= 0) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

// Generate 6-digit PIN
$atmPin = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$tokenReference = 'CASHOUT-' . $reference . '-' . time();

$db->beginTransaction();

try {
    // Store cashout token
    $stmt = $db->prepare("
        INSERT INTO cashout_tokens 
        (token_reference, beneficiary_phone, amount, pin_code, expires_at, source_reference, status)
        VALUES (:ref, :
