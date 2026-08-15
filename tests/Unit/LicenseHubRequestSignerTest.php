<?php

namespace Tests\Unit;

use App\Services\Licensing\LicenseHubRequestSigner;
use PHPUnit\Framework\TestCase;

class LicenseHubRequestSignerTest extends TestCase
{
    /**
     * Matches License Hub's own RequestVerifier byte-for-byte: canonical
     * string is METHOD\nPATH\nTIMESTAMP\nNONCE\nSHA256(BODY), signature is
     * hex-encoded hash_hmac('sha256', ...) — see
     * packages/request-signing/src/{CanonicalRequest,HmacSigner}.php in the
     * License Hub repo. A signature this class produces must verify against
     * an independent implementation of that same formula.
     */
    public function test_signature_matches_the_documented_canonical_request_formula(): void
    {
        $signer = new LicenseHubRequestSigner();
        $headers = $signer->sign('POST', '/api/v1/entitlements/check', '{"workspace_id":"1001"}', 'key-1', 'secret-1');

        $canonical = implode("\n", [
            'POST',
            '/api/v1/entitlements/check',
            $headers['X-DoSieci-Timestamp'],
            $headers['X-DoSieci-Nonce'],
            hash('sha256', '{"workspace_id":"1001"}'),
        ]);
        $expected = hash_hmac('sha256', $canonical, 'secret-1');

        self::assertSame($expected, $headers['X-DoSieci-Signature']);
        self::assertSame('key-1', $headers['X-DoSieci-Key-Id']);
        self::assertSame('v1', $headers['X-DoSieci-Signature-Version']);
        self::assertMatchesRegularExpression('/^\d+$/', $headers['X-DoSieci-Timestamp']);
        self::assertNotEmpty($headers['X-DoSieci-Nonce']);
    }

    public function test_two_signatures_of_the_same_request_use_different_nonces(): void
    {
        $signer = new LicenseHubRequestSigner();
        $first = $signer->sign('POST', '/api/v1/entitlements/check', '{}', 'key', 'secret');
        $second = $signer->sign('POST', '/api/v1/entitlements/check', '{}', 'key', 'secret');

        self::assertNotSame($first['X-DoSieci-Nonce'], $second['X-DoSieci-Nonce']);
        self::assertNotSame($first['X-DoSieci-Signature'], $second['X-DoSieci-Signature']);
    }

    public function test_a_different_secret_produces_a_different_signature(): void
    {
        $signer = new LicenseHubRequestSigner();
        $a = $signer->sign('POST', '/x', 'body', 'key', 'secret-a');
        $b = $signer->sign('POST', '/x', 'body', 'key', 'secret-b');

        self::assertNotSame($a['X-DoSieci-Signature'], $b['X-DoSieci-Signature']);
    }
}
