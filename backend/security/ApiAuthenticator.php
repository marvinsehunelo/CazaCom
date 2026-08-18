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
        $apiKey = $this->extractApiKey($headers);
        
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
    
    /**
     * ============================================================
     * FIX: extractApiKey() (and, before this fix, authenticateFromRequest()
     * directly) was checking $headers['HTTP_X_API_KEY'], ['HTTP_AUTHORIZATION'],
     * ['HTTP_API_KEY'] — the $_SERVER-superglobal naming convention
     * (uppercase, underscored, HTTP_-prefixed). That format is ONLY ever
     * produced by getAllHeaders()'s own $_SERVER fallback loop below, which
     * only runs when the getallheaders() function does not exist. In this
     * environment getallheaders() DOES exist, so that fallback never runs —
     * and getallheaders() itself returns headers keyed by their real
     * on-the-wire names ("X-Api-Key", "Authorization"), never the HTTP_-
     * prefixed form. The result: this lookup failed unconditionally, on
     * every single request, regardless of whether a correct API key was
     * sent — confirmed live via requests carrying a correct X-Api-Key
     * header (visible in this service's own request logs) still being
     * rejected with "Missing or invalid API key".
     *
     * Fixed by normalizing header keys to lowercase and matching against
     * their real names, so this works correctly whichever branch of
     * getAllHeaders() actually produced them.
     * ============================================================
     */
    private function extractApiKey($headers)
    {
        $headersLower = array_change_key_case($headers, CASE_LOWER);

        $apiKey = $headersLower['x-api-key']
            ?? $headersLower['authorization']
            ?? $headersLower['api-key']
            ?? null;

        if (!$apiKey) {
            return null;
        }

        // Remove 'Bearer ' prefix if present
        return preg_replace('/^Bearer\s+/i', '', $apiKey);
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
