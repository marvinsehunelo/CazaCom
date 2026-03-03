<?php
require_once __DIR__."/../config/database.php";
require_once __DIR__."/../services/MintingService.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["error"=>"invalid payload"]);
    exit;
}

/*
SaccusSalis sends:

{
  "reference": "BANKTX12345",
  "phone": "26771234567",
  "amount": 250.00
}
*/

$phone = $data['phone'];
$amount = $data['amount'];
$reference = $data['reference'];

// find user
$stmt = $db->prepare("SELECT id FROM users WHERE phone=?");
$stmt->execute([$phone]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    echo json_encode(["error"=>"user not found"]);
    exit;
}

$mint = new MintingService($db);
$mint->mintFromBank($user['id'], $amount, $reference);

echo json_encode(["status"=>"minted"]);

