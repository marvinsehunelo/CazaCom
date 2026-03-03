<?php
header("Content-Type: application/json");

if (session_status() === PHP_SESSION_NONE) session_start();

// ----------------------------
// Load Config & Models
// ----------------------------
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../models/Session.php";

// Controllers
$controllers = [
    "AuthController" => require_once __DIR__ . "/../controllers/AuthController.php",
    "WalletController" => require_once __DIR__ . "/../controllers/WalletController.php",
    "InstantMoneyController" => require_once __DIR__ . "/../controllers/InstantMoneyController.php",
    "CallController" => require_once __DIR__ . "/../controllers/CallController.php",
    "SmsController" => require_once __DIR__ . "/../controllers/SmsController.php",
    "DataController" => require_once __DIR__ . "/../controllers/DataController.php",
    "MobileMoneyController" => require_once __DIR__ . "/../controllers/MobileMoneyController.php"
];

$database = new Database();
$db = $database->getConnection();

// ----------------------------
// Helpers
// ----------------------------
function requireParams(array $params, array $data) {
    foreach ($params as $param) {
        if (!isset($data[$param])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Missing parameter: $param"]);
            exit;
        }
    }
}

function authenticateApiRequest(PDO $db): int {
    if (!empty($_SESSION['user']['id'])) return $_SESSION['user']['id'];

    if (!empty($_COOKIE['authToken'])) {
        $token = $_COOKIE['authToken'];
        $sessionModel = new Session($db);
        $sessionData = $sessionModel->findByToken($token);
        if ($sessionData) return $sessionData['user_id'];
    }

    http_response_code(401);
    echo json_encode(["status"=>"error","message"=>"No valid session or authentication token provided."]);
    exit;
}

$internalKey = $_SERVER['HTTP_X_INTERNAL_KEY'] ?? null;
$isInternal = ($internalKey === "SACCUS_INTERNAL_KEY_2025");

// ----------------------------
// Parse request
// ----------------------------
$path = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

$json_data = file_get_contents("php://input");
$data = json_decode($json_data, true) ?: $_POST ?: $_GET;
if (!is_array($data)) $data = [];

// ----------------------------
// Define routes
// ----------------------------
$routes = [

    // ----------------------------
    // Wallet Routes
    // ----------------------------
    "wallet/balance" => ["GET", "WalletController", "balance", true, []],
    "wallet/deposit" => ["POST", "WalletController", "deposit", true, ["amount"]],
    "wallet/credit_to_balance" => ["POST", "WalletController", "creditToBalance", true, ["amount"]],
    "wallet/balance_to_credit" => ["POST", "WalletController", "balanceToCredit", true, ["amount"]],
    "wallet/ewallet_to_balance" => ["POST", "WalletController", "ewalletToBalance", false, ["user_id","phone","amount"], true],
    "wallet/balance_to_ewallet" => ["POST", "WalletController", "balanceToEwallet", true, ["amount"]],
    "wallet/ussd_transfer" => ["POST", "WalletController", "ussdTransfer", false, ["phone","amount","pin"]],
    "wallet/saccus_to_mobile_money" => ["POST", "WalletController", "saccusToMobileMoney", true, ["amount","receiver_user_id"]],
    "wallet/mobile_money_to_saccus" => ["POST", "WalletController", "mobileMoneyToSaccus", true, ["amount","receiver_user_id"]],
    "wallet/main_to_mobile_money" => ["POST", "WalletController", "mainToMobileMoney", true, ["amount","receiver_user_id"]],

    // ----------------------------
    // Mobile Money Routes
    // ----------------------------
    "mm/balance" => ["GET", null, null, true, [], function($db, $userId){
        $stmt = $db->prepare("SELECT balance, credit_balance FROM mobile_money_accounts WHERE user_id = :uid");
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'status' => 'success',
            'mobile_money_balance' => $row ? (float)$row['balance'] : 0.00,
            'credit_balance' => $row ? (float)$row['credit_balance'] : 0.00
        ];
    }],

    "mm/send" => ["POST", null, null, true, ["receiver_user_id","amount"], function($db, $userId, $data){
        $amount = (float)$data['amount'];
        $receiverId = (int)$data['receiver_user_id'];

        if($amount <= 0) return ['status'=>'error','message'=>'Invalid amount'];

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT balance FROM mobile_money_accounts WHERE user_id = :uid FOR UPDATE");
            $stmt->execute(['uid'=>$userId]);
            $sender = $stmt->fetch(PDO::FETCH_ASSOC);
            if(!$sender) throw new Exception("Sender account not found");
            if($sender['balance'] < $amount) throw new Exception("Insufficient balance");

            $stmt = $db->prepare("UPDATE mobile_money_accounts SET balance = balance - :amt, last_updated = NOW() WHERE user_id = :uid");
            $stmt->execute(['amt'=>$amount,'uid'=>$userId]);

            $stmt = $db->prepare("UPDATE mobile_money_accounts SET balance = balance + :amt, last_updated = NOW() WHERE user_id = :uid");
            $stmt->execute(['amt'=>$amount,'uid'=>$receiverId]);

            $stmt = $db->prepare("INSERT INTO mobile_money_transactions 
                (user_id, type, amount, recipient_phone, status, wallet_type, reference, created_at) 
                SELECT :uid, 'transfer', :amt, u.phone_number, 'completed', 'mobile_money', :ref, NOW() 
                FROM users u WHERE u.id = :rid");
            $ref = 'MM-TX-' . time() . rand(1000,9999);
            $stmt->execute(['uid'=>$userId,'amt'=>$amount,'rid'=>$receiverId,'ref'=>$ref]);

            $db->commit();
            return ['status'=>'success','message'=>"Transferred BWP$amount to user $receiverId"];
        } catch(Exception $e) {
            $db->rollBack();
            return ['status'=>'error','message'=>$e->getMessage()];
        }
    }],
    
    "mm/history" => ["GET", null, null, true, [], function($db,$userId){
        $stmt = $db->prepare("SELECT id, type, amount, fee, reference, recipient_phone, network, status, wallet_type, created_at, completed_at 
                              FROM mobile_money_transactions 
                              WHERE user_id = :user_id 
                              ORDER BY created_at DESC 
                              LIMIT 50");
        $stmt->execute(['user_id'=>$userId]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ["status"=>"success","transactions"=>$transactions];
    }],

    "mm/deposit" => ["POST", null, null, true, ["amount"], function($db,$userId,$data){
        $amount = (float)$data['amount'];
        if($amount <= 0) return ['status'=>'error','message'=>'Invalid amount'];

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("UPDATE mobile_money_accounts SET balance = balance + :amt, last_updated = NOW() WHERE user_id = :uid");
            $stmt->execute(['amt'=>$amount,'uid'=>$userId]);

            $stmt = $db->prepare("INSERT INTO mobile_money_transactions (user_id, type, amount, status, wallet_type, reference, created_at) VALUES (:uid,'deposit',:amt,'completed','mobile_money',:ref,NOW())");
            $ref = 'MM-DEP-' . time() . rand(1000,9999);
            $stmt->execute(['uid'=>$userId,'amt'=>$amount,'ref'=>$ref]);

            $db->commit();
            return ['status'=>'success','message'=>"Deposited BWP$amount"];
        } catch(Exception $e){
            $db->rollBack();
            return ['status'=>'error','message'=>$e->getMessage()];
        }
    }],

    "mm/withdraw" => ["POST", null, null, true, ["amount"], function($db,$userId,$data){
        $amount = (float)$data['amount'];
        if($amount <= 0) return ['status'=>'error','message'=>'Invalid amount'];

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT balance FROM mobile_money_accounts WHERE user_id = :uid FOR UPDATE");
            $stmt->execute(['uid'=>$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if(!$row) throw new Exception("Account not found");
            if($row['balance'] < $amount) throw new Exception("Insufficient balance");

            $stmt = $db->prepare("UPDATE mobile_money_accounts SET balance = balance - :amt, last_updated = NOW() WHERE user_id = :uid");
            $stmt->execute(['amt'=>$amount,'uid'=>$userId]);

            $stmt = $db->prepare("INSERT INTO mobile_money_transactions (user_id, type, amount, status, wallet_type, reference, created_at) VALUES (:uid,'withdraw',:amt,'completed','mobile_money',:ref,NOW())");
            $ref = 'MM-WD-' . time() . rand(1000,9999);
            $stmt->execute(['uid'=>$userId,'amt'=>$amount,'ref'=>$ref]);

            $db->commit();
            return ['status'=>'success','message'=>"Withdrew BWP$amount"];
        } catch(Exception $e){
            $db->rollBack();
            return ['status'=>'error','message'=>$e->getMessage()];
        }
    }],

    // ----------------------------
    // SMS Routes - NOW INSIDE THE ARRAY
    // ----------------------------
    "sms" => ["GET", null, null, true, [], function($db, $userId) {
        // Get user's phone number
        $stmt = $db->prepare("SELECT phone_number FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ["status" => "error", "message" => "User not found"];
        }
        
        // Get records from sms table
        $stmt = $db->prepare("
            SELECT id, sender_number, target_number, message, cost, direction, created_at 
            FROM sms 
            WHERE user_id = ? OR sender_number = ? OR target_number = ?
            ORDER BY created_at DESC 
            LIMIT 50
        ");
        $stmt->execute([$userId, $user['phone_number'], $user['phone_number']]);
        $smsRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return ["status" => "success", "data" => $smsRecords];
    }],

    "sms/inbox" => ["GET", null, null, true, [], function($db, $userId) {
        // Get user's phone number
        $stmt = $db->prepare("SELECT phone_number FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ["status" => "error", "message" => "User not found"];
        }
        
        // Get from instant_sms_inbox
        $stmt = $db->prepare("
            SELECT id, provider, from_phone, to_phone, message, received_at, parsed_at, processed
            FROM instant_sms_inbox 
            WHERE to_phone = ? OR from_phone = ?
            ORDER BY received_at DESC 
            LIMIT 50
        ");
        $stmt->execute([$user['phone_number'], $user['phone_number']]);
        $inbox = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return ["status" => "success", "data" => $inbox];
    }],

    "sms/outbox" => ["GET", null, null, true, [], function($db, $userId) {
        // Get user's phone number
        $stmt = $db->prepare("SELECT phone_number FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ["status" => "error", "message" => "User not found"];
        }
        
        // Get from instant_sms_outbox
        $stmt = $db->prepare("
            SELECT id, provider, to_phone, from_phone, message, status, attempts, last_attempt_at, created_at, last_error
            FROM instant_sms_outbox 
            WHERE to_phone = ? OR from_phone = ?
            ORDER BY created_at DESC 
            LIMIT 50
        ");
        $stmt->execute([$user['phone_number'], $user['phone_number']]);
        $outbox = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return ["status" => "success", "data" => $outbox];
    }],

    // ----------------------------
    // Instant Money
    // ----------------------------
    "instantmoney/send" => ["POST","InstantMoneyController","sendInstantMoney",true,["recipient_phone","amount","pin"]],
    "instantmoney/redeem" => ["POST","InstantMoneyController","redeemInstantMoney",false,["token","recipient_phone"]],
    "instantmoney/history" => ["GET","InstantMoneyController","getInstantMoneyHistory",true,[]],

    // ----------------------------
    // SMS (existing)
    // ----------------------------
    "sms/send" => ["POST","SmsController","sendSms",false,["recipient_number","message"]],
    "sms/history" => ["GET","SmsController","getHistory",true,[]],

    // ----------------------------
    // Call
    // ----------------------------
    "call/make" => ["POST","CallController","makeCall",true,["recipient","minutes"]],
];

// ----------------------------
// Route dispatch
// ----------------------------
if (!isset($routes[$path])) {
    http_response_code(404);
    echo json_encode(["status"=>"error","message"=>"Invalid route"]);
    exit;
}

$route = $routes[$path];

// Method check
if ($method !== $route[0]) {
    http_response_code(405);
    echo json_encode(["status"=>"error","message"=>"Method not allowed"]);
    exit;
}

// Parameters check
requireParams($route[4], $data);

// Internal route check
if (!empty($route[5]) && !$isInternal) {
    http_response_code(403);
    echo json_encode(["status"=>"error","message"=>"Unauthorized"]);
    exit;
}

// Authenticate if required
$userId = $route[3] ? authenticateApiRequest($db) : null;

// Execute route
try {
    if (isset($route[6]) && is_callable($route[6])) {
        $response = $route[6]($db,$userId,$data);
    } elseif ($route[1] && $route[2]) {
        $controllerName = $route[1];
        $methodName = $route[2];
        $controller = new $controllerName($db);

        if ($methodName === "sendInstantMoney") {
            $response = $controller->$methodName($userId, $data['recipient_phone'], $data['amount'], $data['pin']);
        } elseif ($methodName === "redeemInstantMoney") {
            $response = $controller->$methodName($data['token'],$data['recipient_phone']);
        } elseif ($methodName === "ussdTransfer") {
            $response = $controller->$methodName($data['phone'],$data['amount'],$data['pin']);
        } elseif ($methodName === "sendSms") {
            $response = $controller->$methodName(0,$data['recipient_number'],$data['message']);
        } else {
            $response = $controller->$methodName($userId,$data);
        }
    } else {
        $response = ["status"=>"error","message"=>"Invalid route configuration"];
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
}
