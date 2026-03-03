<?php
require_once __DIR__ . "/WalletController.php";

class CallController {
    private $db;
    private $walletController;
    private $callRatePerMinute;

    public function __construct($db) {
        $this->db = $db;
        $this->walletController = new WalletController($db);
        // Set call rate, you can also fetch from config
        $this->callRatePerMinute = 0.1; // e.g., 0.1 unit per minute
    }

    // Make a call and deduct credits
    public function makeCall($user_id, $minutes, $destination) {
        $cost = $minutes * $this->callRatePerMinute;

        // Deduct from wallet
        $result = $this->walletController->deduct($user_id, $cost, "Call to {$destination} for {$minutes} minutes");
        if ($result['status'] !== 'success') return $result;

        // Optionally, log call in separate table
        $stmt = $this->db->prepare("INSERT INTO calls (user_id, destination, minutes, cost, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $destination, $minutes, $cost]);

        return ["status"=>"success","message"=>"Call completed","deducted"=>$cost,"balance"=>$result['balance']];
    }
}
