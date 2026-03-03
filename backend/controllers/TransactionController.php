<?php
require_once __DIR__ . "/../models/Transaction.php";

class TransactionController {
    private $db;
    private $tx;

    public function __construct($db) {
        $this->db = $db;
        $this->tx = new Transaction($db);
    }

    public function log($user_id, $type, $amount, $description = "") {
        return $this->tx->create($user_id, $type, $amount, $description);
    }

    public function history($user_id) {
        if (!$user_id) {
            return ["status" => "error", "message" => "User ID required"];
        }
        $transactions = $this->tx->getByUser($user_id);
        return ["status" => "success", "transactions" => $transactions];
    }

    public function send($data) {
        if (!isset($data['from_user'], $data['to_user'], $data['amount'])) {
            return ["status" => "error", "message" => "Missing fields"];
        }

        $this->tx->db->beginTransaction();
        try {
            // Deduct from sender
            $wallet = new Wallet($this->db);
            if (!$wallet->deductFunds($data['from_user'], $data['amount'])) {
                throw new Exception("Insufficient balance");
            }

            // Add to receiver
            $wallet->addFunds($data['to_user'], $data['amount']);

            // Log transactions
            $this->log($data['from_user'], "transfer_out", $data['amount'], "Sent to user " . $data['to_user']);
            $this->log($data['to_user'], "transfer_in", $data['amount'], "Received from user " . $data['from_user']);

            $this->tx->db->commit();
            return ["status" => "success", "message" => "Transfer successful"];
        } catch (Exception $e) {
            $this->tx->db->rollBack();
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }
}
