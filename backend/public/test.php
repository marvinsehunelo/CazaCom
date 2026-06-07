<?php
// test_sms.php - Direct SMS test

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Sms.php';

header('Content-Type: application/json');

try {
    $db = getDbConnection();
    $smsModel = new Sms($db);
    
    // Test saving an SMS directly
    $result = $smsModel->saveSms(
        null,               // user_id (null for system)
        'SYSTEM',           // sender_number
        '+26770000000',     // target_number
        'Test message from Cazacom',  // message
        0,                  // cost
        'sent'              // direction
    );
    
    echo json_encode([
        'status' => 'success',
        'message' => 'SMS saved successfully',
        'result' => $result
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
