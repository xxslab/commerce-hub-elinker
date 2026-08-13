<?php

namespace Tests\Feature;

use App\Jobs\SyncSalesChannelOrdersJob;
use App\Models\Company;
use App\Models\SalesChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncSalesChannelOrdersJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_woocommerce_auth_error_is_reported_as_woocommerce(): void
    {
        Http::fake(['*' => Http::response(['message' => 'bad key'], 401)]);

        $company = Company::create(['name' => 'Acme', 'email' => 'acme@example.test']);
        $channel = SalesChannel::create([
            'company_id' => $company->id, 'type' => 'woocommerce', 'name' => 'Sklep',
            'base_url' => 'https://shop.example.test', 'is_enabled' => true,
        ]);
        $channel->setCredentials(['consumer_key' => 'ck', 'consumer_secret' => 'cs']);
        $channel->save();

        (new SyncSalesChannelOrdersJob($channel->id))->handle();

        $channel->refresh();
        self::assertSame('woocommerce_authentication', $channel->last_error_code);
        self::assertStringContainsString('WooCommerce', $channel->last_error);
        self::assertStringNotContainsString('Allegro', $channel->last_error);
    }

    public function test_allegro_auth_error_is_reported_as_allegro_not_woocommerce(): void
    {
        Http::fake(['*' => Http::response(['error' => 'invalid_token'], 401)]);

        $company = Company::create(['name' => 'Acme2', 'email' => 'acme2@example.test']);
        $channel = SalesChannel::create([
            'company_id' => $company->id, 'type' => 'allegro', 'name' => 'Allegro konto', 'is_enabled' => true,
        ]);
        $channel->setCredentials(['access_token' => 'expired-token']);
        $channel->save();

        (new SyncSalesChannelOrdersJob($channel->id))->handle();

        $channel->refresh();
        self::assertSame('allegro_authentication', $channel->last_error_code);
        self::assertStringContainsString('Allegro', $channel->last_error);
        self::assertStringNotContainsString('WooCommerce', $channel->last_error);
    }

    public function test_ebay_rate_limit_is_reported_as_ebay(): void
    {
        Http::fake(['*' => Http::response(['error' => 'rate limited'], 429)]);

        $company = Company::create(['name' => 'Acme3', 'email' => 'acme3@example.test']);
        $channel = SalesChannel::create([
            'company_id' => $company->id, 'type' => 'ebay', 'name' => 'eBay konto', 'is_enabled' => true,
        ]);
        $channel->setCredentials(['access_token' => 'token']);
        $channel->save();

        try {
            (new SyncSalesChannelOrdersJob($channel->id))->handle();
        } catch (\Throwable $e) {
            // Job re-throws so the queue can retry; that's expected here.
        }

        $channel->refresh();
        self::assertSame('ebay_rate_limited', $channel->last_error_code);
        self::assertStringContainsString('eBay', $channel->last_error);
    }
}
