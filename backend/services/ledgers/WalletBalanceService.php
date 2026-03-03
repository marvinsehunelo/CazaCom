<?php

class WalletBalanceService {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getBalance($accountId) {

        $stmt = $this->db->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN credit_account=? THEN amount END),0)
              - COALESCE(SUM(CASE WHEN debit_account=? THEN amount END),0)
            AS balance
            FROM ledger_entries
        ");

        $stmt->execute([$accountId, $accountId]);
        return (float)$stmt->fetch()['balance'];
    }
}

