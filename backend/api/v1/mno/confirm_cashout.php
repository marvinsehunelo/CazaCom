<?php
// Cazacom: Confirm cash was dispensed

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . '/../../config/db.php';

$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents("php://input"), true);
$tokenReference = $input['token_reference'] ?? null;
$dispensedNotes = $input['dispensed_notes'] ?? [];

if (!$tokenReference) {
    echo json_encode(["status" => "error", "message" => "Token reference required"]);
    exit;
}

$db->beginTransaction();

try {
    $stmt = $db->prepare("
        UPDATE cashout_tokens 
        SET status = 'COMPLETED', 
            completed_at = NOW(),
            dispensed_notes = :notes
        WHERE token_reference = :ref AND status = 'ACTIVE'
        RETURNING amount, beneficiary_phone
    ");
    $stmt->execute([
        'notes' => json_encode($dispensedNotes),
        'ref' => $tokenReference
    ]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        throw new Exception("Token not found or already completed");
    }
    
    // Create settlement obligation
    $stmt = $db->prepare("
        INSERT INTO settlement_obligations 
        (reference, from_participant, to_participant, amount, status, created_at)
        VALUES (:ref, 'CAZACOM', 'VOUCHMORPH', :amount, 'PENDING', NOW())
    ");
    $stmt->execute([
        'ref' => 'SETTLE-' . $tokenReference,
        'amount' => $result['amount']
    ]);
    
    $db->commit();
    
    echo json_encode([
        "status" => "success",
        "confirmed" => true,
        "settlement_triggered" => true,
        "settlement_reference" => 'SETTLE-' . $tokenReference
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage(),
        "confirmed" => false
    ]);
}
