<?php

class LedgerService {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function post($debitAccount, $creditAccount, $amount, $type, $refId=null, $note="") {

        if ($amount <= 0) {
            throw new Exception("Invalid amount");
        }

        $stmt = $this->db->prepare("
            INSERT INTO ledger_entries 
            (debit_account, credit_account, amount, reference_type, reference_id, narration)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $debitAccount,
            $creditAccount,
            $amount,
            $type,
            $refId,
            $note
        ]);

        return true;
    }
}

