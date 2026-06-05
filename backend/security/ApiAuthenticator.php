<?php
// cazacom/security/ApiAuthenticator.php

namespace Cazacom\Security;

class ApiAuthenticator
{
    private $pdo;
    private $oauthServer;
    
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->oauthServer = new OAuth2Server($pdo);
    }
    
    /**
     * Validate incoming request from VouchMorph
     * Steps: mTLS → DPoP → Bearer Token
     */
    public function authenticate(): array
    {
        // 1. Validate mTLS certificate
        $certInfo = $this->validateMutualTls();
        
        // 2. Validate DPoP proof
        $dpop = $this->validateDpopProof();
        
        // 3. Validate Bearer token
        $token = $this->validateBearerToken();
        
        // 4. Verify token is bound to this certificate
        if ($token['cnf']['x5t#S256'] !== $this->getCertThumbprint($certInfo)) {
            $this->reject('Token binding failed', 'TOKEN_BINDING_FAILED');
        }
        
        return [
            'client_id' => $token['client_id'],
            'scopes' => $token['scope'],
            'certificate' => $certInfo
        ];
    }
    
    private function validateMutualTls(): array
    {
        $clientCert = $_SERVER['SSL_CLIENT_CERT'] ?? null;
        if (!$clientCert) {
            $this->reject('mTLS certificate required', 'MTLS_REQUIRED');
        }
        
        $certInfo = openssl_x509_parse($clientCert);
        
        if ($certInfo['validTo_time_t'] < time()) {
            $this->reject('Certificate expired', 'CERT_EXPIRED');
        }
        
        // Verify client is VouchMorph
        $allowedCn = ['vouchmorph.railway.app', 'vouchmorph.btccloud.bw'];
        if (!in_array($certInfo['subject']['CN'] ?? '', $allowedCn)) {
            $this->reject('Certificate not authorized', 'UNAUTHORIZED_CERT');
        }
        
        return $certInfo;
    }
    
    private function validateDpopProof(): array
    {
        $dpopHeader = $_SERVER['HTTP_DPOP'] ?? null;
        if (!$dpopHeader) {
            $this->reject('DPoP proof required', 'DPOP_REQUIRED');
        }
        
        $parts = explode('.', $dpopHeader);
        $payload = json_decode($this->base64UrlDecode($parts[1]), true);
        
        // Validate nonce (prevent replay)
        $nonceKey = "dpop_nonce:{$payload['nonce']}";
        $redis = new \Redis();
        $redis->connect(getenv('REDIS_HOST') ?: 'localhost');
        
        if ($redis->exists($nonceKey)) {
            $this->reject('DPoP nonce already used', 'DPOP_REPLAY');
        }
        $redis->setex($nonceKey, 300, '1');
        
        // Validate method and URL
        if ($payload['htm'] !== $_SERVER['REQUEST_METHOD']) {
            $this->reject('DPoP method mismatch', 'DPOP_METHOD');
        }
        
        $requestUrl = strtok($_SERVER['REQUEST_URI'], '?');
        if ($payload['htu'] !== $requestUrl) {
            $this->reject('DPoP URL mismatch', 'DPOP_URL');
        }
        
        return $payload;
    }
    
    private function validateBearerToken(): array
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/DPoP\s+(.+)$/i', $authHeader, $matches)) {
            $this->reject('Bearer token required', 'TOKEN_REQUIRED');
        }
        
        return $this->oauthServer->validateAccessToken($matches[1]);
    }
    
    private function getCertThumbprint(array $certInfo): string
    {
        return base64_encode(hash('sha256', $certInfo['certificate'] ?? '', true));
    }
    
    private function reject(string $message, string $code, int $httpCode = 401): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => $code,
            'error_description' => $message,
            'timestamp' => date('c')
        ]);
        exit;
    }
    
    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
