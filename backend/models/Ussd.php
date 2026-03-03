<?php
class Ussd {
    private $db;

    public function __construct($db){
        $this->db = $db;
    }

    // Save USSD session state
    public function saveSession($session_id, $user_id, $text, $step){
        $stmt = $this->db->prepare("REPLACE INTO ussd_sessions (session_id, user_id, last_text, step, updated_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$session_id, $user_id, $text, $step]);
    }

    public function getSession($session_id){
        $stmt = $this->db->prepare("SELECT * FROM ussd_sessions WHERE session_id=?");
        $stmt->execute([$session_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function clearSession($session_id){
        $stmt = $this->db->prepare("DELETE FROM ussd_sessions WHERE session_id=?");
        $stmt->execute([$session_id]);
    }
}
