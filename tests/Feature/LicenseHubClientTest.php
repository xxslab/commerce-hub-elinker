<?php

namespace Tests\Feature;

use App\Services\Licensing\LicenseHubClient;
use App\Services\Licensing\LicenseHubUnavailableException;
use App\Services\Licensing\ProductLinkRejectedException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LicenseHubClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'commerce-hub.license_hub.url' => 'https://license.example.test',
            'commerce-hub.license_hub.key_id' => 'elinker-key',
            'commerce-hub.license_hub.secret' => 'elinker-secret',
            'commerce-hub.license_hub.timeout' => 5,
        ]);
    }

    public function test_successful_check_returns_decoded_payload(): void
    {
        Http::fake(['license.example.test/*' => Http::response([
            'workspace_id' => '1001', 'active' => true, 'status' => 'active', 'plan_code' => 'pro',
            'features' => [], 'checked_at' => '2026-01-01T00:00:00+00:00',
        ], 200)]);

        $data = (new LicenseHubClient())->checkEntitlement('1001');

        self::assertTrue($data['active']);
        self::assertSame('pro', $data['plan_code']);
    }

    public function test_request_is_signed_with_the_configured_key(): void
    {
        Http::fake(['license.example.test/*' => Http::response(['active' => false], 200)]);

        (new LicenseHubClient())->checkEntitlement('1001');

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-DoSieci-Key-Id', 'elinker-key')
                && $request->hasHeader('X-DoSieci-Signature-Version', 'v1')
                && $request->hasHeader('X-DoSieci-Signature')
                && $request->hasHeader('X-DoSieci-Nonce')
                && $request->hasHeader('X-DoSieci-Timestamp');
        });
    }

    public function test_5xx_response_throws_unavailable_exception(): void
    {
        Http::fake(['license.example.test/*' => Http::response(['error' => 'internal'], 500)]);

        $this->expectException(LicenseHubUnavailableException::class);
        (new LicenseHubClient())->checkEntitlement('1001');
    }

    public function test_401_response_throws_unavailable_exception(): void
    {
        Http::fake(['license.example.test/*' => Http::response(['error' => 'unauthorized'], 401)]);

        $this->expectException(LicenseHubUnavailableException::class);
        (new LicenseHubClient())->checkEntitlement('1001');
    }

    public function test_malformed_json_response_throws_unavailable_exception(): void
    {
        Http::fake(['license.example.test/*' => Http::response('not json at all', 200, ['Content-Type' => 'application/json'])]);

        $this->expectException(LicenseHubUnavailableException::class);
        (new LicenseHubClient())->checkEntitlement('1001');
    }

    public function test_response_missing_the_active_key_throws_unavailable_exception(): void
    {
        Http::fake(['license.example.test/*' => Http::response(['unexpected' => 'shape'], 200)]);

        $this->expectException(LicenseHubUnavailableException::class);
        (new LicenseHubClient())->checkEntitlement('1001');
    }

    public function test_connection_timeout_throws_unavailable_exception(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        });

        $this->expectException(LicenseHubUnavailableException::class);
        (new LicenseHubClient())->checkEntitlement('1001');
    }

    public function test_missing_signing_key_configuration_throws_unavailable_exception(): void
    {
        config(['commerce-hub.license_hub.key_id' => null, 'commerce-hub.license_hub.secret' => null]);

        $this->expectException(LicenseHubUnavailableException::class);
        (new LicenseHubClient())->checkEntitlement('1001');
    }

    public function test_consume_product_link_returns_the_resolved_workspace_id(): void
    {
        Http::fake(['license.example.test/*' => Http::response(['workspace_id' => '3001'], 200)]);

        $workspaceId = (new LicenseHubClient())->consumeProductLink('a-valid-code');

        self::assertSame('3001', $workspaceId);
    }

    public function test_consume_product_link_sends_the_product_and_token_signed(): void
    {
        Http::fake(['license.example.test/*' => Http::response(['workspace_id' => '3001'], 200)]);

        (new LicenseHubClient())->consumeProductLink('a-valid-code');

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $request->url() === 'https://license.example.test/api/v1/product-links/consume'
                && $body['product'] === 'elinker'
                && $body['token'] === 'a-valid-code'
                && $request->hasHeader('X-DoSieci-Signature');
        });
    }

    public function test_consume_product_link_422_throws_rejected_exception(): void
    {
        Http::fake(['license.example.test/*' => Http::response(['error' => 'Token already used.'], 422)]);

        $this->expectException(ProductLinkRejectedException::class);
        $this->expectExceptionMessage('Token already used.');
        (new LicenseHubClient())->consumeProductLink('used-code');
    }

    public function test_consume_product_link_5xx_throws_unavailable_exception_not_rejected(): void
    {
        Http::fake(['license.example.test/*' => Http::response(['error' => 'internal'], 500)]);

        $this->expectException(LicenseHubUnavailableException::class);
        (new LicenseHubClient())->consumeProductLink('some-code');
    }

    public function test_consume_product_link_malformed_response_throws_unavailable_exception(): void
    {
        Http::fake(['license.example.test/*' => Http::response(['unexpected' => 'shape'], 200)]);

        $this->expectException(LicenseHubUnavailableException::class);
        (new LicenseHubClient())->consumeProductLink('some-code');
    }

    public function test_consume_product_link_connection_timeout_throws_unavailable_exception(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        });

        $this->expectException(LicenseHubUnavailableException::class);
        (new LicenseHubClient())->consumeProductLink('some-code');
    }
}
