<?php

namespace App\Services\Licensing;

/**
 * Client-side implementation of License Hub's request-signing protocol
 * (packages/request-signing in the License Hub repo — see its
 * docs/adr/0008-api-request-signing-hmac.md). Deliberately re-implements
 * the same documented wire protocol from this side rather than depending on
 * that repo's private package: headers X-DoSieci-Key-Id/Timestamp/Nonce/
 * Signature/Signature-Version, canonical string
 * METHOD\nPATH\nTIMESTAMP\nNONCE\nSHA256(BODY), HMAC-SHA256.
 *
 * $path must be exactly what the Hub's VerifySignedRequest middleware
 * reconstructs server-side — Symfony's Request::getPathInfo(), i.e. the URL
 * path only (leading slash, no scheme/host, no query string).
 */
class LicenseHubRequestSigner
{
    public function sign(string $method, string $path, string $body, string $keyId, string $secret): array
    {
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $canonical = implode("\n", [
            strtoupper($method),
            $path,
            $timestamp,
            $nonce,
            hash('sha256', $body),
        ]);
        $signature = hash_hmac('sha256', $canonical, $secret);

        return [
            'X-DoSieci-Key-Id' => $keyId,
            'X-DoSieci-Timestamp' => $timestamp,
            'X-DoSieci-Nonce' => $nonce,
            'X-DoSieci-Signature' => $signature,
            'X-DoSieci-Signature-Version' => 'v1',
        ];
    }
}
