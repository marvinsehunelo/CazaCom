<?php
// routes/api.php - CAZACOM VERSION with Handshake Support

header("Content-Type: application/json");

if (session_status() === PHP_SESSION_NONE) session_start();

// ----------------------------
// Load Config & Models
// ----------------------------
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../models/Session.php";
require_once __DIR__ . "/../security/KeyVault.php";
require_once __DIR__ . "/../security/ApiAuthenticator.php";

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

// NEW: Authenticate API key from participants (VouchMorph, Saccussalis, Zurubank)
function authenticateParticipantRequest(PDO $db, $expectedParticipant = null) {
    $authenticator = new Security\ApiAuthenticator();
    
    if ($expectedParticipant) {
        return $authenticator->requireAuth($expectedParticipant);
    }
    
    $participant = $authenticator->authenticateFromRequest();
    if (!$participant) {
        http_response_code(401);
        echo json_encode(["status"=>"error","message"=>"Invalid or missing API key"]);
        exit;
    }
    
    return $participant;
}

// Check internal key (for Cazacom's internal services)
$internalKey = $_SERVER['HTTP_X_INTERNAL_KEY'] ?? null;
$isInternal = ($internalKey === "CAZACOM_INTERNAL_KEY_2025");

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

    // ============================================================
    // TEST ROUTE - Check environment variables
    // ============================================================
    
    "test/env" => ["GET", null, null, false, [], function($db, $userId, $data) {
        return [
            "status" => "success",
            "cazacom_api_key_configured" => !empty(getenv('CAZACOM_API_KEY')),
            "cazacom_api_key_prefix" => getenv('CAZACOM_API_KEY') ? substr(getenv('CAZACOM_API_KEY'), 0, 10) . '...' : null,
            "railway_env" => getenv('RAILWAY_ENVIRONMENT') ?: 'not_set',
            "all_vars" => array_keys(array_filter($_SERVER, function($key) {
                return strpos($key, 'CAZACOM') !== false || strpos($key, 'RAILWAY') !== false;
            }, ARRAY_FILTER_USE_KEY))
        ];
    }],

    // ============================================================
    // HANDSHAKE ROUTES - for Cazacom to connect with partners
    // ============================================================
    
    // Get handshake status with all participants
    "handshake/status" => ["GET", null, null, false, [], function($db, $userId) {
        $keyVault = Security\Encryption\KeyVault::getInstance();
        $participants = $keyVault->getParticipants();
        $status = [];
        
        foreach ($participants as $p) {
            $outgoingConfig = $keyVault->getUpstreamConfig($p);
            $incomingKey = $keyVault->getIncomingKey($p);
            
            $status[$p] = [
                'incoming_key_configured' => !empty($incomingKey),
                'outgoing_key_configured' => !empty($outgoingConfig['api_key']),
                'base_url_configured' => !empty($outgoingConfig['base_url']),
                'header_name' => $outgoingConfig['header_name'],
                'timeout_seconds' => $outgoingConfig['timeout']
            ];
        }
        
        return [
            "status" => "success", 
            "participants" => $status,
            "cazacom_api_key" => !empty(getenv('CAZACOM_API_KEY')) ? "configured" : "missing"
        ];
    }],
    
    // Test handshake with a specific participant
    "handshake/test" => ["POST", null, null, false, ["participant"], function($db, $userId, $data) {
        $participant = $data['participant'];
        $keyVault = Security\Encryption\KeyVault::getInstance();
        
        $config = $keyVault->getUpstreamConfig($participant);
        
        if (!$config['base_url']) {
            return [
                "status" => "error",
                "message" => "No base URL configured for {$participant}"
            ];
        }
        
        $startTime = microtime(true);
        
        $ch = curl_init($config['base_url'] . '/api/v1/health');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $config['timeout']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $headers = [
            'Content-Type: application/json',
            'X-Source: cazacom',
            'X-Timestamp: ' . time()
        ];
        
        if ($config['api_key']) {
            $headers[] = $config['header_name'] . ': ' . $config['api_key'];
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $duration = (microtime(true) - $startTime) * 1000;
        $error = curl_error($ch);
        curl_close($ch);
        
        return [
            "status" => $httpCode >= 200 && $httpCode < 300 ? "success" : "error",
            "participant" => $participant,
            "http_code" => $httpCode,
            "duration_ms" => round($duration, 2),
            "response" => json_decode($response, true),
            "error" => $error ?: null
        ];
    }],
    
    // Webhook receiver for VouchMorph to call Cazacom
    "webhook/vouchmorph" => ["POST", null, null, false, [], function($db, $userId, $data) {
        $authenticator = new Security\ApiAuthenticator();
        $participant = $authenticator->requireAuth('vouchmorph');
        
        $action = $data['action'] ?? null;
        
        switch($action) {
            case 'airtime_purchase':
                $phone = $data['phone'] ?? null;
                $amount = $data['amount'] ?? null;
                $reference = $data['reference'] ?? null;
                
                if (!$phone || !$amount) {
                    return ["status" => "error", "message" => "Missing phone or amount"];
                }
                
                return [
                    "status" => "success",
                    "message" => "Airtime purchase processed",
                    "reference" => $reference,
                    "amount" => $amount,
                    "phone" => $phone
                ];
                
            case 'balance_inquiry':
                $phone = $data['phone'] ?? null;
                return [
                    "status" => "success",
                    "balance" => 100.00,
                    "currency" => "BWP",
                    "phone" => $phone
                ];
                
            default:
                return ["status" => "error", "message" => "Unknown action: {$action}"];
        }
    }],
    
    // Webhook receiver for Saccussalis to call Cazacom
    "webhook/saccussalis" => ["POST", null, null, false, [], function($db, $userId, $data) {
        $authenticator = new Security\ApiAuthenticator();
        $participant = $authenticator->requireAuth('saccussalis');
        
        return [
            "status" => "success",
            "message" => "Webhook received from Saccussalis",
            "data" => $data
        ];
    }],
    
    // Webhook receiver for Zurubank to call Cazacom
    "webhook/zurubank" => ["POST", null, null, false, [], function($db, $userId, $data) {
        $authenticator = new Security\ApiAuthenticator();
        $participant = $authenticator->requireAuth('zurubank');
        
        return [
            "status" => "success",
            "message" => "Webhook received from Zurubank",
            "data" => $data
        ];
    }],
    
    // Webhook receiver for Zurubank-SA to call Cazacom
    "webhook/zurubank_sa" => ["POST", null, null, false, [], function($db, $userId, $data) {
        $authenticator = new Security\ApiAuthenticator();
        $participant = $authenticator->requireAuth('zurubank_sa');
        
        return [
            "status" => "success",
            "message" => "Webhook received from Zurubank-SA",
            "data" => $data
        ];
    }],

    // ============================================================
    // EXISTING WALLET ROUTES
    // ============================================================
    
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

    // ============================================================
    // EXISTING MOBILE MONEY ROUTES
    // ============================================================
    
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

    // ============================================================
    // EXISTING SMS ROUTES - FIXED to accept both + and non+ formats
    // ============================================================
    
    "sms" => ["GET", null, null, true, [], function($db, $userId) {
        $stmt = $db->prepare("SELECT phone_number FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ["status" => "error", "message" => "User not found"];
        }
        
        // ============================================================
        // FIX: Search for both + and non+ formats
        // ============================================================
        $phone = $user['phone_number'];
        $phoneWithPlus = (strpos($phone, '+') === 0) ? $phone : '+' . $phone;
        $phoneWithoutPlus = ltrim($phone, '+');
        
        $stmt = $db->prepare("
            SELECT id, sender_number, target_number, message, cost, direction, created_at 
            FROM sms 
            WHERE user_id = ?
               OR sender_number IN (:phone1, :phone2)
               OR target_number IN (:phone1, :phone2)
            ORDER BY created_at DESC 
            LIMIT 50
        ");
        $stmt->execute([
            $userId,
            'phone1' => $phoneWithPlus,
            'phone2' => $phoneWithoutPlus
        ]);
        $smsRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Normalize phone numbers in results (always show with +)
        foreach ($smsRecords as &$record) {
            if (isset($record['sender_number']) && strpos($record['sender_number'], '+') !== 0) {
                $record['sender_number'] = '+' . $record['sender_number'];
            }
            if (isset($record['target_number']) && strpos($record['target_number'], '+') !== 0) {
                $record['target_number'] = '+' . $record['target_number'];
            }
        }
        
        return ["status" => "success", "data" => $smsRecords];
    }],

    "sms/inbox" => ["GET", null, null, true, [], function($db, $userId) {
        $stmt = $db->prepare("SELECT phone_number FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ["status" => "error", "message" => "User not found"];
        }
        
        // ============================================================
        // FIX: Search for both + and non+ formats
        // ============================================================
        $phone = $user['phone_number'];
        $phoneWithPlus = (strpos($phone, '+') === 0) ? $phone : '+' . $phone;
        $phoneWithoutPlus = ltrim($phone, '+');
        
        $stmt = $db->prepare("
            SELECT id, provider, from_phone, to_phone, message, received_at, parsed_at, processed
            FROM instant_sms_inbox 
            WHERE to_phone IN (:phone1, :phone2) OR from_phone IN (:phone1, :phone2)
            ORDER BY received_at DESC 
            LIMIT 50
        ");
        $stmt->execute([
            'phone1' => $phoneWithPlus,
            'phone2' => $phoneWithoutPlus
        ]);
        $inbox = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Normalize phone numbers in results
        foreach ($inbox as &$record) {
            if (isset($record['from_phone']) && strpos($record['from_phone'], '+') !== 0) {
                $record['from_phone'] = '+' . $record['from_phone'];
            }
            if (isset($record['to_phone']) && strpos($record['to_phone'], '+') !== 0) {
                $record['to_phone'] = '+' . $record['to_phone'];
            }
        }
        
        return ["status" => "success", "data" => $inbox];
    }],

    "sms/outbox" => ["GET", null, null, true, [], function($db, $userId) {
        $stmt = $db->prepare("SELECT phone_number FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ["status" => "error", "message" => "User not found"];
        }
        
        // ============================================================
        // FIX: Search for both + and non+ formats
        // ============================================================
        $phone = $user['phone_number'];
        $phoneWithPlus = (strpos($phone, '+') === 0) ? $phone : '+' . $phone;
        $phoneWithoutPlus = ltrim($phone, '+');
        
        $stmt = $db->prepare("
            SELECT id, provider, to_phone, from_phone, message, status, attempts, last_attempt_at, created_at, last_error
            FROM instant_sms_outbox 
            WHERE to_phone IN (:phone1, :phone2) OR from_phone IN (:phone1, :phone2)
            ORDER BY created_at DESC 
            LIMIT 50
        ");
        $stmt->execute([
            'phone1' => $phoneWithPlus,
            'phone2' => $phoneWithoutPlus
        ]);
        $outbox = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Normalize phone numbers in results
        foreach ($outbox as &$record) {
            if (isset($record['from_phone']) && strpos($record['from_phone'], '+') !== 0) {
                $record['from_phone'] = '+' . $record['from_phone'];
            }
            if (isset($record['to_phone']) && strpos($record['to_phone'], '+') !== 0) {
                $record['to_phone'] = '+' . $record['to_phone'];
            }
        }
        
        return ["status" => "success", "data" => $outbox];
    }],

    // ============================================================
    // EXISTING INSTANT MONEY ROUTES
    // ============================================================
    
    "instantmoney/send" => ["POST","InstantMoneyController","sendInstantMoney",true,["recipient_phone","amount","pin"]],
    "instantmoney/redeem" => ["POST","InstantMoneyController","redeemInstantMoney",false,["token","recipient_phone"]],
    "instantmoney/history" => ["GET","InstantMoneyController","getInstantMoneyHistory",true,[]],

    // ============================================================
    // EXISTING SMS SEND/HISTORY
    // ============================================================
    
    "sms/send" => ["POST","SmsController","sendSms",false,["recipient_number","message"]],
    "sms/history" => ["GET","SmsController","getHistory",true,[]],

    // ============================================================
    // EXISTING CALL ROUTES
    // ============================================================
    
    "call/make" => ["POST","CallController","makeCall",true,["recipient","minutes"]],

    // ============================================================
    // TRANSACTIONS ROUTE - NEW
    // ============================================================
    
    "transactions" => ["GET", "WalletController", "getTransactions", true, []],

    // ============================================================
    // HEALTH CHECK for handshake testing
    // ============================================================
    
    "v1/health" => ["GET", null, null, false, [], function($db, $userId, $data) {
        return [
            "status" => "healthy",
            "service" => "cazacom",
            "version" => "1.0.0",
            "timestamp" => time()
        ];
    }],
];

// ----------------------------
// Route dispatch
// ----------------------------
if (!isset($routes[$path])) {
    http_response_code(404);
    echo json_encode(["status"=>"error","message"=>"Invalid route: " . $path]);
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

// Internal route check (the 6th element is internal flag)
if (isset($route[5]) && $route[5] === true && !$isInternal) {
    http_response_code(403);
    echo json_encode(["status"=>"error","message"=>"Unauthorized - Internal endpoint"]);
    exit;
}

// Authenticate if required (3rd element is auth flag)
$userId = null;
if ($route[3] === true) {
    $userId = authenticateApiRequest($db);
}

// Execute route
try {
    if (isset($route[6]) && is_callable($route[6])) {
        $response = $route[6]($db, $userId, $data);
    } elseif ($route[1] && $route[2]) {
        $controllerName = $route[1];
        $methodName = $route[2];
        
        if (!class_exists($controllerName)) {
            throw new Exception("Controller not found: $controllerName");
        }
        
        $controller = new $controllerName($db);

               if ($methodName === "sendInstantMoney") {
            $response = $controller->$methodName($userId, $data['recipient_phone'], $data['amount'], $data['pin']);
        } elseif ($methodName === "redeemInstantMoney") {
            $response = $controller->$methodName($data['token'], $data['recipient_phone']);
        } elseif ($methodName === "ussdTransfer") {
            $response = $controller->$methodName($data['phone'], $data['amount'], $data['pin']);
        } elseif ($methodName === "sendSms") {
            // Fixed: recipient_number, message, userId
            $response = $controller->$methodName($data['recipient_number'], $data['message'], $userId);
        } else {
            $response = $controller->$methodName($userId, $data);
        }
    } else {
        $response = ["status"=>"error","message"=>"Invalid route configuration"];
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
}
