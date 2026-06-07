<?php
// services/HandshakeService.php - CAZACOM VERSION

require_once __DIR__ . '/../security/KeyVault.php';

use Security\Encryption\KeyVault;

class HandshakeService
{
    private $keyVault;
    
    public function __construct()
    {
        $this->keyVault = KeyVault::getInstance();
    }
    
    /**
     * Test handshake with a participant
     */
    public function testHandshake($participant)
    {
        $config = $this->keyVault->getUpstreamConfig($participant);
        
        if (!$config['base_url']) {
            return [
                'success' => false,
                'error' => "No base URL configured for {$participant}"
            ];
        }
        
        $startTime = microtime(true);
        
        $ch = curl_init($config['base_url'] . '/api/v1/health');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $config['timeout']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing only
        
        if ($config['api_key']) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                $config['header_name'] . ': ' . $config['api_key'],
                'Content-Type: application/json',
                'X-Source: cazacom',
                'X-Timestamp: ' . time()
            ]);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $duration = (microtime(true) - $startTime) * 1000;
        $error = curl_error($ch);
        curl_close($ch);
        
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'participant' => $participant,
            'http_code' => $httpCode,
            'duration_ms' => round($duration, 2),
            'response' => json_decode($response, true),
            'error' => $error ?: null,
            'config' => [
                'url' => $config['base_url'],
                'header' => $config['header_name'],
                'has_key' => !empty($config['api_key'])
            ]
        ];
    }
    
    /**
     * Get handshake status for all participants
     */
    public function getStatus()
    {
        $participants = $this->keyVault->getParticipants();
        $status = [];
        
        foreach ($participants as $p) {
            $incomingKey = $this->keyVault->getIncomingKey($p);
            $outgoingConfig = $this->keyVault->getUpstreamConfig($p);
            
            $status[$p] = [
                'incoming_key_configured' => !empty($incomingKey),
                'outgoing_key_configured' => !empty($outgoingConfig['api_key']),
                'base_url_configured' => !empty($outgoingConfig['base_url']),
                'handshake_tested' => false,
                'last_test_success' => null
            ];
        }
        
        return $status;
    }
}
