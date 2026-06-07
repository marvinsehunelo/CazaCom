<?php
// security/KeyVault.php

namespace Security\Encryption;

require_once __DIR__ . '/../config/db.php';

use PDO;

class KeyVault
{
    private static $instance = null;
    private $encryptionKey;
    private $db;
    private $cache = [];
    
    // Participants
    const PARTICIPANT_CAZACOM = 'cazacom';
    const PARTICIPANT_VOUCHMORPH = 'vouchmorph';
    const PARTICIPANT_SACCUSSALIS = 'saccussalis';
    const PARTICIPANT_ZURUBANK = 'zurubank';
    const PARTICIPANT_ZURUBANK_SA = 'zurubank_sa';
    
    private function __construct()
    {
        // Get database connection
        $this->db = getDbConnection();
        
        // Get encryption key from Railway vault FIRST, fallback to DB
        $this->encryptionKey = getenv('ENCRYPTION_KEY');
        
        if (!$this->encryptionKey) {
            // Try to get from database
            $this->encryptionKey = $this->getKeyFromDb('master_encryption_key');
            
            if (!$this->encryptionKey) {
                // Generate and store in DB (but warn about Railway)
                $this->encryptionKey = bin2hex(random_bytes(32));
                $this->storeKeyInDb('master_encryption_key', $this->encryptionKey);
                error_log("WARNING: ENCRYPTION_KEY not in Railway vault. Using DB stored key.");
            }
        }
        
        // Load Railway env vars into vault_kv_store on first run
        $this->syncRailwayEnvToDb();
    }
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Sync Railway environment variables to vault_kv_store
     */
    private function syncRailwayEnvToDb()
    {
        $railwayVars = [
            'CAZACOM_API_KEY' => getenv('CAZACOM_API_KEY'),
            'CAZACOM_BASE_URL' => getenv('CAZACOM_BASE_URL'),
            'CAZACOM_API_HEADER' => getenv('CAZACOM_API_HEADER'),
            'UPSTREAM_CAZACOM_KEY' => getenv('UPSTREAM_CAZACOM_KEY'),
            'SACCUSSALIS_API_KEY' => getenv('SACCUSSALIS_API_KEY'),
            'SACCUSSALIS_BASE_URL' => getenv('SACCUSSALIS_BASE_URL'),
            'UPSTREAM_SACCUSSALIS_KEY' => getenv('UPSTREAM_SACCUSSALIS_KEY'),
            'ZURUBANK_API_KEY' => getenv('ZURUBANK_API_KEY'),
            'ZURUBANK_BASE_URL' => getenv('ZURUBANK_BASE_URL'),
            'UPSTREAM_ZURUBANK_KEY' => getenv('UPSTREAM_ZURUBANK_KEY'),
            'ZURUBANK_SA_API_KEY' => getenv('ZURUBANK_SA_API_KEY'),
            'ZURUBANK_SA_BASE_URL' => getenv('ZURUBANK_SA_BASE_URL'),
            'UPSTREAM_ZURUBANK_SA_KEY' => getenv('UPSTREAM_ZURUBANK_SA_KEY'),
        ];
        
        foreach ($railwayVars as $key => $value) {
            if ($value && !$this->getFromVaultStore('/railway/env', $key)) {
                $this->storeInVaultStore('/railway/env', $key, $value);
            }
        }
    }
    
    /**
     * Get value from vault_kv_store
     */
    private function getFromVaultStore($parentPath, $key)
    {
        $cacheKey = $parentPath . ':' . $key;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        $stmt = $this->db->prepare("
            SELECT value FROM vault_kv_store 
            WHERE parent_path = :parent AND key = :key
        ");
        $stmt->execute(['parent' => $parentPath, 'key' => $key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['value']) {
            // Value is stored as BYTEA, decrypt if encrypted
            $value = stream_get_contents($result['value']);
            $this->cache[$cacheKey] = $value;
            return $value;
        }
        
        return null;
    }
    
    /**
     * Store value in vault_kv_store
     */
    private function storeInVaultStore($parentPath, $key, $value)
    {
        $stmt = $this->db->prepare("
            INSERT INTO vault_kv_store (parent_path, path, key, value)
            VALUES (:parent, :parent || '/' || :key, :key, :value)
            ON CONFLICT (path, key) DO UPDATE SET value = EXCLUDED.value
        ");
        
        // Store as BYTEA
        return $stmt->execute([
            'parent' => $parentPath,
            'key' => $key,
            'value' => $value
        ]);
    }
    
    /**
     * Get key from encryption_keys table
     */
    private function getKeyFromDb($keyId)
    {
        $stmt = $this->db->prepare("
            SELECT key_value FROM encryption_keys 
            WHERE key_id = :id AND active = true
        ");
        $stmt->execute(['id' => $keyId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Decrypt if encrypted
            return $this->decrypt($result['key_value']) ?: $result['key_value'];
        }
        
        return null;
    }
    
    /**
     * Store key in encryption_keys table
     */
    private function storeKeyInDb($keyId, $keyValue)
    {
        // Encrypt before storing
        $encrypted = $this->encrypt($keyValue);
        
        $stmt = $this->db->prepare("
            INSERT INTO encryption_keys (key_id, key_value, active, created_at)
            VALUES (:id, :value, true, NOW())
            ON CONFLICT (key_id) WHERE active = true 
            DO UPDATE SET key_value = EXCLUDED.key_value
        ");
        
        return $stmt->execute(['id' => $keyId, 'value' => $encrypted ?: $keyValue]);
    }
    
    /**
     * Get API key for authenticating incoming requests
     * Priority: Railway Env > vault_kv_store > encryption_keys
     */
    public function getIncomingKey($participant)
    {
        $envKey = getenv(strtoupper($participant) . '_API_KEY');
        if ($envKey) {
            return $envKey;
        }
        
        $vaultKey = $this->getFromVaultStore('/api_keys/incoming', $participant);
        if ($vaultKey) {
            return $vaultKey;
        }
        
        $dbKey = $this->getKeyFromDb('incoming_' . $participant);
        if ($dbKey) {
            return $dbKey;
        }
        
        error_log("Missing incoming API key for participant: {$participant}");
        return null;
    }
    
    /**
     * Validate incoming API key
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
     * Get upstream config for calling a participant
     */
    public function getUpstreamConfig($participant)
    {
        $baseUrl = getenv(strtoupper($participant) . '_BASE_URL');
        $apiKey = getenv('UPSTREAM_' . strtoupper($participant) . '_KEY');
        $header = getenv(strtoupper($participant) . '_API_HEADER');
        
        if (!$baseUrl) {
            $baseUrl = $this->getFromVaultStore('/upstream/configs', $participant . '_base_url');
        }
        if (!$apiKey) {
            $apiKey = $this->getFromVaultStore('/upstream/configs', $participant . '_api_key');
        }
        if (!$header) {
            $header = $this->getFromVaultStore('/upstream/configs', $participant . '_header') ?: 'X-API-Key';
        }
        
        return [
            'api_key' => $apiKey,
            'header_name' => $header,
            'base_url' => $baseUrl,
            'rate_limit' => $this->getRateLimit($participant),
        ];
    }
    
    /**
     * Get rate limit for participant
     */
    public function getRateLimit($participant)
    {
        $rateLimit = getenv(strtoupper($participant) . '_RATE_LIMIT');
        if ($rateLimit) {
            return (int)$rateLimit;
        }
        
        $rateLimit = $this->getFromVaultStore('/rate_limits', $participant);
        return $rateLimit ? (int)$rateLimit : 500;
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
        if (strlen($data) < 16) {
            return $encryptedData; // Not encrypted
        }
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
    }
    
    /**
     * Rotate key in encryption_keys table
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
            
            // Clear cache
            unset($this->cache[$keyId]);
            
            return $newKey;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Key rotation failed: " . $e->getMessage());
            return false;
        }
    }
}
