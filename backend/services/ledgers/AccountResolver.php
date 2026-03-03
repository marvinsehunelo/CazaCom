<?php

class AccountResolver {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getCustomerLiabilityAccount($userId) {
        $stmt = $this->db->prepare("
            SELECT ledger_account_id 
            FROM wallet_accounts WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        if (!$row) throw new Exception("Wallet account missing");

        return $row['ledger_account_id'];
    }

    public function getTrustSafeguardAccount() {
        $stmt = $this->db->query("
            SELECT id FROM ledger_accounts 
            WHERE account_code = 'TRUST_SAFEGUARD'
            LIMIT 1
        ");
        return $stmt->fetch()['id'];
    }

    public function getRevenueAccount() {
        $stmt = $this->db->query("
            SELECT id FROM ledger_accounts 
            WHERE account_code = 'FEE_REVENUE'
            LIMIT 1
        ");
        return $stmt->fetch()['id'];
    }
}

