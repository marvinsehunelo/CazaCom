<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);
$amount = $data['amount'] ?? null;
$phone  = $data['phone'] ?? null;

if (!$amount || !$phone) {
    echo json_encode(["status"=>"error","message"=>"Amount and phone are required"]);
    exit;
}

// Fetch wallet
$stmt = $pdo->prepare("SELECT balance, credit_balance FROM wallets WHERE phone = ? LIMIT 1");
$stmt->execute([$phone]);
$wallet = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$wallet) {
    echo json_encode(["status"=>"error","message"=>"Wallet not found"]);
    exit;
}

if ($wallet['balance'] < $amount) {
    echo json_encode(["status"=>"error","message"=>"Insufficient balance"]);
    exit;
}

// Perform conversion
$stmt = $pdo->prepare("
    UPDATE wallets
    SET balance = balance - :amount,
        credit_balance = credit_balance + :amount
    WHERE phone = :phone
");
$stmt->execute([
    ':amount' => $amount,
    ':phone' => $phone
]);

$stmt = $pdo->prepare("SELECT balance, credit_balance FROM wallets WHERE phone = ? LIMIT 1");
$stmt->execute([$phone]);
$updated = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    "status"=>"success",
    "message"=>"Balance converted to credit successfully",
    "balance"=>$updated['balance'],
    "credit_balance"=>$updated['credit_balance']
]);
