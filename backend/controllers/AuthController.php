<?php

// Ensure the User model is loaded to handle core authentication logic
require_once __DIR__ . '/../models/User.php'; 

class AuthController {
    protected $db;

    /**
     * AuthController constructor.
     * @param PDO $db The database connection instance.
     */
    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Handles user login authentication, token generation, and data sanitization.
     * @param array $data Contains 'phone_number' and 'pin'.
     * @return array Status, sanitized user data, and generated token on success.
     */
    public function login($data) {
        // 1. Initialize User model to interact with the database
        $userModel = new User($this->db);
        
        // 2. Authenticate user using phone number and pin
        // ASSUMPTION: User::login returns the full user array on success (including hashes), or false.
        $authUser = $userModel->login($data['phone_number'], $data['pin']);
        
        if ($authUser) {
            // 3. Generate a secure, unique token
            // This token will be stored in the 'sessions' table and sent back to the client.
            $token = bin2hex(random_bytes(32)); 
            
            // 4. SECURITY FIX: Sanitize the user data before returning
            // Crucial step to prevent sensitive data (like password/pin hashes) from
            // being exposed or stored in the PHP session/cookie.
            unset($authUser['pin_hash']); 
            unset($authUser['password']); // In case a 'password' column exists
            
            // 5. Return success response with the sanitized user data and token
            return [
                "status" => "success", 
                "user" => $authUser, // Sanitized array
                "token" => $token    // Generated token
            ];
        }
        
        // 6. Authentication failed
        return ["status" => "error", "message" => "Invalid credentials"];
    }
}
