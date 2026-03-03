<?php
require_once __DIR__."/ledgers/LedgerService.php";
require_once __DIR__."/ledgers/AccountResolver.php";

class MobileMoneyService {

    private PDO $db;
    private LedgerService $ledger;
    private AccountResolver $resolver;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->ledger = new LedgerService($db);
        $this->resolver = new AccountResolver($db);
    }

    public function transfer($senderId, $receiverId, $amount) {

        $this->db->beginTransaction();

        try {

            $senderAccount   = $this->resolver->getCustomerLiabilityAccount($senderId);
            $receiverAccount = $this->resolver->getCustomerLiabilityAccount($receiverId);

            // LIABILITY MOVES BETWEEN CUSTOMERS
            $this->ledger->post(
                $senderAccount,
                $receiverAccount,
                $amount,
                "P2P_TRANSFER",
                null,
                "Wallet to wallet transfer"
            );

            $this->db->commit();

            return ["status"=>"success"];

        } catch(Exception $e) {
            $this->db->rollBack();
            return ["status"=>"error","message"=>$e->getMessage()];
        }
    }
}

