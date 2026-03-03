<?php
require_once __DIR__."/ledger/LedgerService.php";
require_once __DIR__."/ledger/AccountResolver.php";

class MintingService {

    private PDO $db;
    private LedgerService $ledger;
    private AccountResolver $resolver;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->ledger = new LedgerService($db);
        $this->resolver = new AccountResolver($db);
    }

    public function mintFromBank($userId, $amount, $bankReference) {

        $this->db->beginTransaction();

        try {

            // TRUST ACCOUNT (asset)
            $trustAccount = $this->resolver->getTrustSafeguardAccount();

            // CUSTOMER WALLET (liability)
            $customerAccount = $this->resolver->getCustomerLiabilityAccount($userId);

            // Bank money becomes customer liability
            $this->ledger->post(
                $trustAccount,
                $customerAccount,
                $amount,
                "CASHIN",
                $bankReference,
                "Bank deposit mint"
            );

            // record deposit
            $stmt = $this->db->prepare("
                INSERT INTO cashin_requests(user_id, amount, bank_reference, status)
                VALUES (?, ?, ?, 'completed')
            ");
            $stmt->execute([$userId, $amount, $bankReference]);

            $this->db->commit();

            return true;

        } catch(Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}

