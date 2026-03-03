<?php
require_once __DIR__ . "/WalletController.php";

class DataController {
    private $db;
    private $walletController;

    public function __construct($db) {
        $this->db = $db;
        $this->walletController = new WalletController($db);
    }

    // Purchase internet bundle
    public function purchaseBundle($user_id, $bundle_id) {
        // Fetch bundle price from database
        $stmt = $this->db->prepare("SELECT name, price, data_mb FROM data_bundles WHERE id = ?");
        $stmt->execute([$bundle_id]);
        $bundle = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bundle) return ["status"=>"error","message"=>"Bundle not found"];

        // Deduct wallet
        $result = $this->walletController->deduct($user_id, $bundle['price'], "Purchased data bundle '{$bundle['name']}' ({$bundle['data_mb']} MB)");
        if ($result['status'] !== 'success') return $result;

        // Log subscription
        $stmt = $this->db->prepare("INSERT INTO subscriptions (user_id, bundle_id, data_mb, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, $bundle_id, $bundle['data_mb']]);

        return ["status"=>"success","message"=>"Bundle purchased","deducted"=>$bundle['price'],"balance"=>$result['balance']];
    }
}
