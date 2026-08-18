<?php
// security/KeyVault.php - CAZACOM VERSION

namespace Security\Encryption;

require_once __DIR__ . '/../config/db.php';

use PDO;

class KeyVault
{
    private static $instance = null;
    private $encryptionKey;
    private $db;
    private $cache = [];
    
    // Participants Cazacom needs to handshake with
    const PARTICIPANT_VOUCHMORPH = 'vouchmorph';
    const PARTICIPANT_SACCUSSALIS = 'saccussalis';
    const PARTICIPANT_ZURUBANK = 'zurubank';
    const PARTICIPANT_ZURUBANK_SA = 'zurubank_sa';
    
    private function __construct()
    {
        // ============================================================
        // FIX: getDbConnection() does not exist anywhere in this
        // codebase. config/db.php defines an unnamespaced `Database`
        // class (instantiated as `new Database()` -> ->getConnection(),
        // the same pattern hold.php/credit.php/debit.php already use
        // successfully) and a `getDB()` functional wrapper — never a
        // bare `getDbConnection()` function. Since this file lives in
        // namespace Security\Encryption, the old unqualified call was
        // resolving to Security\Encryption\getDbConnection(), which
        // has never existed, causing an uncaught fatal Error the
        // moment ApiAuthenticator (and therefore every Cazacom
        // endpoint that authenticates via ApiAuthenticator — hold.php,
        // credit.php, debit.php) tried to construct a KeyVault. The
        // fatal happened before any JSON response was emitted, which
        // is why callers upstream (GenericBankClient) saw an empty/
        // unparseable body and fell back to their own generic
        // "Hold failed"/"Deposit failed" text instead of a real error
        // message — confirmed live via:
        //   Uncaught Error: Call to undefined function
        //   Security\Encryption\getDbConnection() in
        //   /app/security/KeyVault.php:26
        //
        // `Database` is declared with no namespace (global), so it
        // must be referenced with a leading backslash from inside
        // this namespaced class.
        // ============================================================
        $database = new \Database();
        $this->db = $database->getConnection();

        if (!$this->db) {
            error_log("KeyVault: Database connection failed via Database::getConnection()");
        }
        
        // Get encryption key from Railway vault
        $this->encryptionKey = getenv('ENCRYPTION_KEY');
        
        if (!$this->encryptionKey) {
            // Try to get from database
            $this->encryptionKey = $this->getKeyFromDb('cazacom_master_key');
            
            if (!$this->encryptionKey) {
                // Generate and store
                $this->encryptionKey = bin2hex(random_bytes(32));
                $this->storeKeyInDb('cazacom_master_key', $this->encryptionKey);
                error_log("WARNING: ENCRYPTION_KEY not in Railway vault. Using DB stored key.");
            }
        }
    }
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get key from encryption_keys table
     */
    private function getKeyFromDb($keyId)
    {
        if (isset($this->cache[$keyId])) {
            return $this->cache[$keyId];
        }
        
        $stmt = $this->db->prepare("
            SELECT key_value FROM encryption_keys 
            WHERE key_id = :id AND active = true
        ");
        $stmt->execute(['id' => $keyId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $value = $result['key_value'];
            // Try to decrypt if it looks encrypted
            $decrypted = $this->decrypt($value);
            $this->cache[$keyId] = $decrypted ?: $value;
            return $this->cache[$keyId];
        }
        
        return null;
    }
    
    /**
     * Store key in encryption_keys table
     */
    private function storeKeyInDb($keyId, $keyValue)
    {
        $encrypted = $this->encrypt($keyValue);
        
        $stmt = $this->db->prepare("
            INSERT INTO encryption_keys (key_id, key_value, active, created_at)
            VALUES (:id, :value, true, NOW())
            ON CONFLICT (key_id, key_value) DO NOTHING
        ");
        
        return $stmt->execute([
            'id' => $keyId,
            'value' => $encrypted ?: $keyValue
        ]);
    }
    
    /**
     * Get API key for authenticating incoming requests from a participant
     * (What VouchMorph/Saccussalis/Zurubank sends TO Cazacom)
     */
    public function getIncomingKey($participant)
    {
        // First check Railway vault
        $envKey = getenv(strtoupper($participant) . '_API_KEY');
        if ($envKey) {
            return $envKey;
        }
        
        // Then check database
        $dbKey = $this->getKeyFromDb('incoming_' . $participant);
        if ($dbKey) {
            return $dbKey;
        }
        
        error_log("Missing incoming API key for participant: {$participant}");
        return null;
    }
    
    /**
     * Validate an incoming API key from a participant
     */
    public function validateIncomingKey($participant, $providedKey)
    {
        $expectedKey = $this->getIncomingKey($participant);
        
        if (!$expectedKey) {
            return false;
        }
        
        return hash_equals($expectedKey, $providedKey);
    }
    
    /**
     * Get API key for OUTGOING requests (Cazacom → Participant)
     * (What Cazacom sends TO VouchMorph/Saccussalis/Zurubank)
     */
    public function getOutgoingKey($participant)
    {
        // First check Railway vault
        $envKey = getenv('UPSTREAM_' . strtoupper($participant) . '_KEY');
        if ($envKey) {
            return $envKey;
        }
        
        // Then check database
        $dbKey = $this->getKeyFromDb('outgoing_' . $participant);
        if ($dbKey) {
            return $dbKey;
        }
        
        error_log("Missing outgoing API key for participant: {$participant}");
        return null;
    }
    
    /**
     * Get upstream configuration for calling a participant
     */
    public function getUpstreamConfig($participant)
    {
        $baseUrl = getenv(strtoupper($participant) . '_BASE_URL');
        $apiKey = $this->getOutgoingKey($participant);
        $header = getenv(strtoupper($participant) . '_API_HEADER');
        $timeout = (int)(getenv(strtoupper($participant) . '_TIMEOUT') ?: 10);
        
        // Default headers by participant
        $defaultHeaders = [
            'vouchmorph' => 'X-API-Key',
            'saccussalis' => 'Authorization',
            'zurubank' => 'X-API-Key',
            'zurubank_sa' => 'X-API-Key',
        ];
        
        return [
            'api_key' => $apiKey,
            'header_name' => $header ?: ($defaultHeaders[$participant] ?? 'X-API-Key'),
            'base_url' => $baseUrl,
            'timeout' => $timeout,
        ];
    }
    
    /**
     * Get all configured participants
     */
    public function getParticipants()
    {
        return [
            self::PARTICIPANT_VOUCHMORPH,
            self::PARTICIPANT_SACCUSSALIS,
            self::PARTICIPANT_ZURUBANK,
            self::PARTICIPANT_ZURUBANK_SA,
        ];
    }
    
    /**
     * Encrypt data
     */
    public function encrypt($data, $context = 'default')
    {
        if (empty($data)) {
            return null;
        }
        
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt data
     */
    public function decrypt($encryptedData, $context = 'default')
    {
        if (empty($encryptedData)) {
            return null;
        }
        
        $data = base64_decode($encryptedData);
        if ($data === false || strlen($data) < 16) {
            return $encryptedData; // Not encrypted
        }
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        
        return $decrypted ?: $encryptedData;
    }
    
    /**
     * Rotate a key
     */
    public function rotateKey($keyId)
    {
        $newKey = bin2hex(random_bytes(32));
        $encrypted = $this->encrypt($newKey);
        
        $this->db->beginTransaction();
        
        try {
            // Deactivate old key
            $stmt = $this->db->prepare("
                UPDATE encryption_keys 
                SET active = false, retired_at = NOW() 
                WHERE key_id = :id AND active = true
            ");
            $stmt->execute(['id' => $keyId]);
            
            // Insert new key
            $stmt = $this->db->prepare("
                INSERT INTO encryption_keys (key_id, key_value, active, created_at)
                VALUES (:id, :value, true, NOW())
            ");
            $stmt->execute(['id' => $keyId, 'value' => $encrypted ?: $newKey]);
            
            $this->db->commit();
            unset($this->cache[$keyId]);
            
            return $newKey;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Key rotation failed: " . $e->getMessage());
            return false;
        }
    }
}
