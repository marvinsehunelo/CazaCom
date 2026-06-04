<?php
// Cazacom: Final debit after successful transaction

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . '/../../config/db.php';

$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents("php://input"), true);
$holdReference = $input['hold_reference'] ?? null;
$amount = (float)($input['amount'] ?? 0);
$destinationDetails = $input['destination_details'] ?? [];

if (!$holdReference) {
    echo json_encode(["status" => "error", "message" => "Hold reference required"]);
    exit;
}

$db->beginTransaction();

try {
    // Get hold details
    $stmt = $db->prepare("SELECT * FROM financial_holds WHERE hold_reference = :ref AND status = 'HELD' FOR UPDATE");
    $stmt->execute(['ref' => $holdReference]);
    $hold = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$hold) {
        throw new Exception("Hold not found");
    }
    
    // Update hold to committed
    $stmt = $db->prepare("
        UPDATE financial_holds 
        SET status = 'COMMITTED', 
            committed_at = NOW(),
            destination = :dest
        WHERE hold_reference = :ref
    ");
    $stmt->execute([
        'dest' => json_encode($destinationDetails),
        'ref' => $holdReference
    ]);
    
    // Update wallet held balance (funds are now permanently debited)
    $stmt = $db->prepare("
        UPDATE mobile_money_accounts 
        SET held_balance = held_balance - :amount,
            last_updated = NOW()
        WHERE user_id = :user_id
    ");
    $stmt->execute(['amount' => $hold['amount'], 'user_id' => $hold['user_id']]);
    
    // Record transaction
    $transactionRef = 'TX-' . time() . '-' . bin2hex(random_bytes(8));
    $stmt = $db->prepare("
        INSERT INTO mobile_money_transactions 
        (user_id, type, amount, reference, status, completed_at, destination)
        VALUES (:user_id, 'debit', :amount, :ref, 'completed', NOW(), :dest)
    ");
    $stmt->execute([
        'user_id' => $hold['user_id'],
        'amount' => $hold['amount'],
        'ref' => $transactionRef,
        'dest' => json_encode($destinationDetails)
    ]);
    
    $db->commit();
    
    echo json_encode([
        "status" => "success",
        "debited" => true,
        "transaction_reference" => $transactionRef,
        "amount" => (float)$hold['amount']
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage(),
        "debited" => false
    ]);
}
