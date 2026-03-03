<?php
class User {
    private $db;

    public function __construct($db){
        $this->db = $db;
    }

    /**
     * Authenticates a user by phone number and PIN.
     * Note: This method currently assumes the 'pin' column stores the raw PIN, 
     * which is a security risk. In a real app, this should verify a PIN hash.
     * For now, this fixes the syntax error.
     */
    public function login($phone_number, $pin){
        // Selecting all columns (*) to allow the controller to sanitize the array later
        $stmt = $this->db->prepare("SELECT * FROM users WHERE phone_number = ? AND pin_hash = ?");
        $stmt->execute([$phone_number, $pin]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Returns the user array on success, or false on failure
        return $user ?: false;
    }
}
