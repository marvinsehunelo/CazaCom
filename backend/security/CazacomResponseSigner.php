<?php
declare(strict_types=1);

/**
 * CazacomResponseSigner
 * ============================================================
 * Signs CAZACOM's outgoing API responses so VouchMorph's
 * SignatureVerifier can validate them, closing the gap that caused
 * "Invalid signature from: CAZACOM" during multi-source pool
 * assembly (AggregateSigner::signAggregate()).
 *
 * Root cause this fixes: CAZACOM's hold.php / verify_wallet.php never
 * signed their responses at all (no signing library was ever wired
 * in) — confirmed by hold_transactions.signature_chain showing
 * "signature": null for every CAZACOM hold. In a single-source swap
 * nothing checks for this, so it was invisible; the moment CAZACOM
 * appears as one of several contributors in a pool, AggregateSigner
 * tries to fold in each contributor's response signature and fails
 * hard for CAZACOM.
 *
 * ============================================================
 * ⚠️ THIS NEEDS A HANDSHAKE WITH VOUCHMORPH BEFORE IT WILL WORK ⚠️
 * ============================================================
 * Generating a certificate and signing responses only closes HALF
 * the gap. VouchMorph's SignatureVerifier must also be told to trust
 * this certificate — the same way it already trusts ZURUBANK's and
 * SACCUSSALIS's self-signed / CA-issued certs. Until someone with
 * access to VouchMorph's trust store (participants.yaml / wherever
 * SignatureVerifier's known-certificates list lives) registers
 * CAZACOM's new certificate, verification will still fail — just
 * with a different error (untrusted cert) instead of "no signature
 * at all."
 *
 * I have NOT seen VouchMorph's actual SignatureVerifier
 * implementation, only its observable behavior (accepts ZURUBANK's
 * and SACCUSSALIS's signatures, rejects CAZACOM's absence of one).
 * The canonicalization below (ksort + json_encode, matching the
 * "SIGNING JSON" pattern visible in VouchMorph's own logs) is my
 * best inference from those logs, NOT confirmed against real
 * SignatureVerifier source. If verification still fails after this
 * is deployed and the cert is registered, the most likely next
 * culprit is a canonicalization mismatch (key order, whitespace,
 * which fields are included/excluded) — get SignatureVerifier.php
 * from the VouchMorph side to confirm exactly what it re-serializes
 * before hashing, and adjust signPayload() below to match exactly.
 * ============================================================
 */
class CazacomResponseSigner
{
    private string $privateKeyPem;
    private string $certificatePem;

    /**
     * @param string $privateKeyPath  Path to the PEM private key.
     *   NEVER commit this file to source control. Load it the same
     *   way this codebase loads other secrets (env var pointing at a
     *   secrets-manager-mounted path, not a path inside the repo).
     * @param string $certificatePath Path to the PEM certificate —
     *   this one IS meant to be shared (it's sent in every signed
     *   response), so it's fine for it to live in a config directory.
     */
    public function __construct(string $privateKeyPath, string $certificatePath)
    {
        if (!is_readable($privateKeyPath)) {
            throw new \RuntimeException("CAZACOM signing private key not found or unreadable: {$privateKeyPath}");
        }
        if (!is_readable($certificatePath)) {
            throw new \RuntimeException("CAZACOM signing certificate not found or unreadable: {$certificatePath}");
        }
        $this->privateKeyPem = file_get_contents($privateKeyPath);
        $this->certificatePem = file_get_contents($certificatePath);
    }

    /**
     * Signs a response payload and returns the signature + certificate
     * to attach to the JSON response.
     *
     * @param array $payload The response fields being signed. Pass the
     *   full response array (before adding 'signature'/'certificate'
     *   themselves) so the signature covers everything the caller
     *   will read.
     * @return array{signature: string, certificate: string, timestamp: int}
     */
    public function sign(array $payload): array
    {
        $timestamp = time();
        $payload['timestamp'] = $timestamp;

        $canonical = $this->canonicalize($payload);

        $privateKey = openssl_pkey_get_private($this->privateKeyPem);
        if ($privateKey === false) {
            throw new \RuntimeException('Failed to load CAZACOM private key: ' . openssl_error_string());
        }

        $signature = '';
        $signed = openssl_sign($canonical, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (!$signed) {
            throw new \RuntimeException('Failed to sign CAZACOM response: ' . openssl_error_string());
        }

        return [
            'signature' => base64_encode($signature),
            'certificate' => $this->certificatePem,
            'timestamp' => $timestamp,
        ];
    }

    /**
     * Canonicalization: sort keys, then json_encode.
     *
     * ⚠️ BEST-EFFORT — see class docblock. Confirmed pattern from
     * VouchMorph's own "CertificateManager: SIGNING JSON" log lines
     * (their signed payloads appear key-sorted), NOT confirmed
     * against actual SignatureVerifier source. If signatures still
     * fail to verify after the cert is registered, check this first.
     */
    private function canonicalize(array $payload): string
    {
        ksort($payload);
        return json_encode($payload, JSON_UNESCAPED_SLASHES);
    }
}
