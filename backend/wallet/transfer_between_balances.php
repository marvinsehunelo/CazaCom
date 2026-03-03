<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

$data = json_decode(file_get_contents("php://input"), true);

$phone = $data['phone'] ?? null;
$from = $data['from'] ?? null; // 'saccus' | 'balance' | 'credit'
$to   = $data['to'] ?? null;   // 'saccus' | 'balance' | 'credit'
$amount = $data['amount'] ?? null;

if (!$phone || !$from || !$to || !$amount) {
    echo json_encode(["status"=>"error","message"=>"phone, from, to, and amount are required"]);
    exit;
}

$stmt = $pdo->prepare("SELECT balance, credit_balance, saccus_ewallet_balance FROM wallets WHERE phone = ? LIMIT 1");
$stmt->execute([$phone]);
$wallet = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$wallet) {
    echo json_encode(["status"=>"error","message"=>"Wallet not found"]);
    exit;
}

// Determine fee
$fee = 0;
if (($from === 'saccus' && $to === 'balance') || ($from === 'balance' && $to === 'saccus')) {
    $fee = 1.00;
}

// Check sufficient funds
$current_amount = $wallet[$from . ($from === 'saccus' ? '_ewallet_balance' : '')];
if ($current_amount < $amount + $fee) {
    echo json_encode(["status"=>"error","message"=>"Insufficient funds in $from"]);
    exit;
}

// Deduct from source
$wallet[$from . ($from === 'saccus' ? '_ewallet_balance' : '')] -= ($amount + $fee);

// Add to target
$wallet[$to . ($to === 'saccus' ? '_ewallet_balance' : '')] += $amount;

// Update DB
$stmt = $pdo->prepare("
    UPDATE wallets 
    SET balance = ?, 
        credit_balance = ?, 
        saccus_ewallet_balance = ? 
    WHERE phone = ?
");
$stmt->execute([
    $wallet['balance'],
    $wallet['credit_balance'],
    $wallet['saccus_ewallet_balance'],
    $phone
]);

echo json_encode([
    "status"=>"success",
    "message"=>"Transfer successful",
    "fee"=>$fee,
    "wallet"=>$wallet
]);
