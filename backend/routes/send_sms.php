<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../config/db.php";   // Cazacom DB
require_once __DIR__ . "/../controllers/SmsController.php";

$database = new Database();
$db = $database->getConnection();

// Accept POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Only POST allowed"]);
    exit;
}

// Get JSON or POST body
$raw = file_get_contents("php://input");
$data = json_decode($raw, true) ?: $_POST;

// --- API Key check ---
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $data['api_key'] ?? null;
$validApiKey = "CAZACOM_LOCAL_KEY_123";
if (!$apiKey || $apiKey !== $validApiKey) {
    http_response_code(403);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid or missing API key"
    ]);
    exit;
}

// Required fields
if (empty($data['target_number']) || empty($data['message'])) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Missing: target_number or message"
    ]);
    exit;
}

// Default values
$userId = $data['user_id'] ?? 9999; // system user
$senderNumber = $data['sender_number'] ?? "SWAP_SYSTEM";
$cost = floatval($data['cost'] ?? 0.1);

try {
    $sms = new SmsController($db);
    $result = $sms->sendSms(
        $userId,
        $data['target_number'],
        $data['message'],
        $cost,
        $senderNumber
    );

    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}

