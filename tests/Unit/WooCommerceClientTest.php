<?php

namespace Tests\Unit;

use App\Models\SalesChannel;
use App\Services\Integrations\WooCommerce\WooCommerceClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WooCommerceClientTest extends TestCase
{
    public function test_connection_checks_https_and_api_access(): void
    {
        Http::fake(['https://shop.example.test/*' => Http::response([], 200)]);
        $channel = new SalesChannel(['base_url' => 'https://shop.example.test']);
        $channel->setCredentials(['consumer_key' => 'ck_test', 'consumer_secret' => 'cs_test']);
        self::assertTrue((new WooCommerceClient($channel))->testConnectionDetails()['ok']);
    }

    public function test_authentication_error_returns_actionable_message(): void
    {
        Http::fake(['https://shop.example.test/*' => Http::response([], 401)]);
        $channel = new SalesChannel(['base_url' => 'https://shop.example.test']);
        $channel->setCredentials(['consumer_key' => 'ck_test', 'consumer_secret' => 'cs_test']);
        $result = (new WooCommerceClient($channel))->testConnectionDetails();
        self::assertFalse($result['ok']);
        self::assertStringContainsString('klucz', strtolower($result['message']));
    }
}
