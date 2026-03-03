<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/SmsController.php";

// --- Input ---
$data = json_decode(file_get_contents("php://input"), true);
$sender_user_id = $data['sender_user_id'] ?? null;
$recipient_phone = $data['recipient_phone'] ?? null;
$amount = $data['amount'] ?? null;

if (!$sender_user_id || !$recipient_phone || !$amount) {
    echo json_encode(["status"=>"error","message"=>"sender_user_id, recipient_phone, and amount are required"]);
    exit;
}

// --- Debit sender bank account ---
$stmt = $pdo->prepare("SELECT bank_balance FROM users WHERE user_id=? LIMIT 1");
$stmt->execute([$sender_user_id]);
$sender = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sender || $sender['bank_balance'] < $amount) {
    echo json_encode(["status"=>"error","message"=>"Insufficient bank account balance"]);
    exit;
}

$new_bank_balance = $sender['bank_balance'] - $amount;
$stmt = $pdo->prepare("UPDATE users SET bank_balance=? WHERE user_id=?");
$stmt->execute([$new_bank_balance, $sender_user_id]);

// --- Credit recipient (CazaCom wallet) ---
$stmt = $pdo->prepare("SELECT balance FROM wallets WHERE phone=? LIMIT 1");
$stmt->execute([$recipient_phone]);
$recipient_wallet = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$recipient_wallet) {
    echo json_encode(["status"=>"error","message"=>"Recipient wallet not found"]);
    exit;
}

$new_recipient_balance = $recipient_wallet['balance'] + $amount;
$stmt = $pdo->prepare("UPDATE wallets SET balance=? WHERE phone=?");
$stmt->execute([$new_recipient_balance, $recipient_phone]);

// --- Generate PIN for cash-out ---
$pin = random_int(100000, 999999);
$stmt = $pdo->prepare("INSERT INTO ewallet_pins (transaction_id, pin, expires_at, created_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 15 MINUTE), NOW())");
$stmt->execute([0, $pin]); // transaction_id = 0 for now

// --- Log transaction ---
$stmt = $pdo->prepare("INSERT INTO transactions (user_id, to_account, recipient_phone, amount, type, status, created_at) VALUES (?,?,?,?, 'ewallet', 'completed', NOW())");
$stmt->execute([$sender_user_id, $recipient_phone, $recipient_phone, $amount]);
$transaction_id = $pdo->lastInsertId();

// --- Send SMS to recipient ---
$sms = new SmsController($pdo);
$sms_result = $sms->sendSms(0, $recipient_phone, "You received P$amount in your wallet. PIN: $pin. New balance: P$new_recipient_balance");

echo json_encode([
    "status"=>"success",
    "message"=>"eWallet transfer successful",
    "transaction_id"=>$transaction_id,
    "recipient_phone"=>$recipient_phone,
    "pin"=>$pin,
    "sms_response"=>$sms_result
]);
