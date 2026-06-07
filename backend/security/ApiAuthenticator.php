<?php
// security/ApiAuthenticator.php - CAZACOM VERSION

namespace Security;

require_once __DIR__ . '/KeyVault.php';

use Security\Encryption\KeyVault;

class ApiAuthenticator
{
    private $keyVault;
    
    public function __construct()
    {
        $this->keyVault = KeyVault::getInstance();
    }
    
    /**
     * Authenticate incoming request from a specific participant
     */
    public function authenticate($participant, $providedKey)
    {
        return $this->keyVault->validateIncomingKey($participant, $providedKey);
    }
    
    /**
     * Get authenticated participant from request headers
     * Tries to match against all known participants
     */
    public function authenticateFromRequest()
    {
        $headers = $this->getAllHeaders();
        
        // Check common API key header locations
        $apiKey = null;
        foreach (['HTTP_X_API_KEY', 'HTTP_AUTHORIZATION', 'HTTP_API_KEY'] as $header) {
            if (isset($headers[$header])) {
                $apiKey = $headers[$header];
                // Remove 'Bearer ' prefix if present
                $apiKey = preg_replace('/^Bearer\s+/i', '', $apiKey);
                break;
            }
        }
        
        if (!$apiKey) {
            return null;
        }
        
        // Try to match against all participants
        foreach ($this->keyVault->getParticipants() as $participant) {
            if ($this->authenticate($participant, $apiKey)) {
                return $participant;
            }
        }
        
        return null;
    }
    
    /**
     * Require authentication for an endpoint
     */
    public function requireAuth($specificParticipant = null)
    {
        if ($specificParticipant) {
            $headers = $this->getAllHeaders();
            $apiKey = $this->extractApiKey($headers);
            
            if (!$apiKey || !$this->authenticate($specificParticipant, $apiKey)) {
                $this->sendUnauthorized("Invalid API key for {$specificParticipant}");
            }
            
            return $specificParticipant;
        }
        
        $participant = $this->authenticateFromRequest();
        
        if (!$participant) {
            $this->sendUnauthorized("Missing or invalid API key");
        }
        
        return $participant;
    }
    
    private function extractApiKey($headers)
    {
        foreach (['HTTP_X_API_KEY', 'HTTP_AUTHORIZATION', 'HTTP_API_KEY'] as $header) {
            if (isset($headers[$header])) {
                $key = $headers[$header];
                return preg_replace('/^Bearer\s+/i', '', $key);
            }
        }
        return null;
    }
    
    private function getAllHeaders()
    {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }
        
        // Fallback for non-Apache servers
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[$name] = $value;
            }
        }
        return $headers;
    }
    
    private function sendUnauthorized($message)
    {
        http_response_code(401);
        header('WWW-Authenticate: API-Key');
        echo json_encode([
            'error' => 'unauthorized',
            'message' => $message,
            'timestamp' => time()
        ]);
        exit;
    }
}
