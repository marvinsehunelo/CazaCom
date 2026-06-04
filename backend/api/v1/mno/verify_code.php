<?php
// Cazacom: Verify cashout code

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . '/../../config/db.php';

$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents("php://input"), true);
$tokenReference = $input['token_reference'] ?? null;
$enteredCode = $input['entered_code'] ?? null;

if (!$tokenReference || !$enteredCode) {
    echo json_encode(["status" => "error", "message" => "Token reference and code required"]);
    exit;
}

$stmt = $db->prepare("
    SELECT * FROM cashout_tokens 
    WHERE token_reference = :ref AND status = 'ACTIVE' AND expires_at > NOW()
");
$stmt->execute(['ref' => $tokenReference]);
$token = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$token) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid or expired token",
        "verified" => false
    ]);
    exit;
}

if (!password_verify($enteredCode, $token['pin_code'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid PIN",
        "verified" => false
    ]);
    exit;
}

echo json_encode([
    "status" => "success",
    "verified" => true,
    "amount" => (float)$token['amount'],
    "beneficiary" => $token['beneficiary_phone'],
    "expires_at" => $token['expires_at']
]);
