<?php
// Cazacom: Credit funds to wallet (for incoming swaps)

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . '/../../config/db.php';

$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents("php://input"), true);
$reference = $input['reference'] ?? null;
$destinationId = $input['destination_id'] ?? null;
$amount = (float)($input['amount'] ?? 0);
$sourceHoldReference = $input['source_hold_reference'] ?? null;

if (!$reference || !$destinationId || $amount <= 0) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

$db->beginTransaction();

try {
    // Get user by phone
    $stmt = $db->prepare("SELECT id FROM users WHERE phone_number = :phone");
    $stmt->execute(['phone' => $destinationId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("User not found");
    }
    
    // Credit wallet
    $stmt = $db->prepare("
        UPDATE mobile_money_accounts 
        SET balance = balance + :amount,
            last_updated = NOW()
        WHERE user_id = :user_id
    ");
    $stmt->execute(['amount' => $amount, 'user_id' => $user['id']]);
    
    // Record transaction
    $transactionRef = 'CREDIT-' . $reference;
    $stmt = $db->prepare("
        INSERT INTO mobile_money_transactions 
        (user_id, type, amount, reference, status, completed_at, source_reference)
        VALUES (:user_id, 'credit', :amount, :ref, 'completed', NOW(), :src)
    ");
    $stmt->execute([
        'user_id' => $user['id'],
        'amount' => $amount,
        'ref' => $transactionRef,
        'src' => $sourceHoldReference
    ]);
    
    $db->commit();
    
    // Get new balance
    $stmt = $db->prepare("SELECT balance FROM mobile_money_accounts WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user['id']]);
    $newBalance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "status" => "success",
        "processed" => true,
        "transaction_reference" => $transactionRef,
        "new_balance" => (float)$newBalance['balance']
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage(),
        "processed" => false
    ]);
}
