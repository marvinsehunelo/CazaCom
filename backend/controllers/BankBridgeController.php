<?php
require_once __DIR__ . '/../services/BankBridgeService.php';

class BankBridgeController {
    private $db;
    private $service;

    public function __construct($db) {
        $this->db = $db;
        $this->service = new BankBridgeService($db);
    }

    public function cashOut($data) {
        return $this->service->cashOut(
            $data['bank'],
            $data['user_id'],
            $data['amount'],
            $data['target_account']
        );
    }

    public function createEwallet($data) {
        return $this->service->createEwallet(
            $data['bank'],
            $data['user'],
            $data['initial_balance'] ?? 0
        );
    }

    public function autoBridge($data) {
        return $this->service->autoBridge(
            $data['from_bank'],
            $data['to_bank'],
            $data['user_id'],
            $data['amount'],
            $data['target_account'],
            $data['user']
        );
    }
}
