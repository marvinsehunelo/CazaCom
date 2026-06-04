<?php
// Cazacom: Place hold on wallet funds

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . '/../../config/db.php';

$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents("php://input"), true);
$reference = $input['reference'] ?? null;
$assetId = $input['asset_id'] ?? null;
$amount = (float)($input['amount'] ?? 0);
$expiry = $input['expiry'] ?? date('Y-m-d H:i:s', strtotime('+24 hours'));
$accessToken = $input['access_token'] ?? null;

if (!$reference || !$assetId || $amount <= 0) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

$db->beginTransaction();

try {
    // Get user and wallet
    $stmt = $db->prepare("SELECT id, phone_number FROM users WHERE phone_number = :phone");
    $stmt->execute(['phone' => $assetId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("User not found");
    }
    
    // Check current balance
    $stmt = $db->prepare("SELECT balance, held_balance FROM mobile_money_accounts WHERE user_id = :user_id FOR UPDATE");
    $stmt->execute(['user_id' => $user['id']]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $availableBalance = (float)$wallet['balance'];
    
    if ($availableBalance < $amount) {
        throw new Exception("Insufficient balance");
    }
    
    // Create hold
    $holdReference = 'HOLD-' . $reference . '-' . time();
    $stmt = $db->prepare("
        INSERT INTO financial_holds 
        (hold_reference, user_id, amount, status, expires_at, created_at, source_reference)
        VALUES (:ref, :user_id, :amount, 'HELD', :expires, NOW(), :src_ref)
    ");
    $stmt->execute([
        'ref' => $holdReference,
        'user_id' => $user['id'],
        'amount' => $amount,
        'expires' => $expiry,
        'src_ref' => $reference
    ]);
    
    // Update wallet held balance
    $stmt = $db->prepare("
        UPDATE mobile_money_accounts 
        SET held_balance = held_balance + :amount, 
            balance = balance - :amount,
            last_updated = NOW()
        WHERE user_id = :user_id
    ");
    $stmt->execute(['amount' => $amount, 'user_id' => $user['id']]);
    
    $db->commit();
    
    echo json_encode([
        "status" => "success",
        "hold_placed" => true,
        "hold_reference" => $holdReference,
        "hold_expiry" => $expiry,
        "amount_held" => $amount,
        "available_balance" => $availableBalance - $amount
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage(),
        "hold_placed" => false
    ]);
}
