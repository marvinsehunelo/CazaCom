<?php
// cazacom/security/OAuth2Server.php

namespace Cazacom\Security;

class OAuth2Server
{
    private $pdo;
    private $privateKey;
    private $publicKey;
    
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->loadKeys();
    }
    
    private function loadKeys(): void
    {
        // Load eIDAS qualified certificates
        $this->privateKey = openssl_pkey_get_private(
            file_get_contents(getenv('EIDAS_PRIVATE_KEY_PATH'))
        );
        $this->publicKey = openssl_pkey_get_public(
            file_get_contents(getenv('EIDAS_PUBLIC_KEY_PATH'))
        );
    }
    
    public function validateAccessToken(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \Exception('Invalid token format');
        }
        
        $payload = json_decode($this->base64UrlDecode($parts[1]), true);
        
        // Check expiration
        if ($payload['exp'] < time()) {
            throw new \Exception('Token expired');
        }
        
        // Check signature
        $signatureInput = $parts[0] . '.' . $parts[1];
        $valid = openssl_verify(
            $signatureInput,
            $this->base64UrlDecode($parts[2]),
            $this->publicKey,
            OPENSSL_ALGO_SHA256
        );
        
        if ($valid !== 1) {
            throw new \Exception('Invalid signature');
        }
        
        return $payload;
    }
    
    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
