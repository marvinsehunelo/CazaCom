<?php
class Data {
    private $db;

    public function __construct($db){
        $this->db = $db;
    }

    public function activateBundle($user_id, $bundle_name){
        $stmt = $this->db->prepare("INSERT INTO data_subscriptions (user_id, bundle_name, activated_at, expires_at) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))");
        $stmt->execute([$user_id, $bundle_name]);
        return true;
    }
}
