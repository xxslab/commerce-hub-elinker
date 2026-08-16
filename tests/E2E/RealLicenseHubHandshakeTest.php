<?php

namespace Tests\E2E;

use App\Models\Company;
use App\Models\User;
use App\Services\Licensing\LicenseHubClient;
use App\Services\Licensing\LicenseHubRequestSigner;
use App\Services\Licensing\LicenseHubUnavailableException;
use App\Services\Licensing\ProductLinkRejectedException;
use App\Services\Licensing\SubscriptionEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * REAL cross-repo handshake test: no Http::fake() anywhere in this class.
 * Every call through LicenseHubClient is a genuine HTTP request over a real
 * TCP socket to an ACTUAL running License Hub Laravel process. This is
 * deliberately NOT part of the default `php artisan test` run -- see
 * phpunit.xml, which only registers tests/Unit and tests/Feature; running
 * this file requires a live Hub instance, which CI does not have.
 *
 * Requires two env vars set by the caller (see scripts/e2e/run.sh, which
 * boots a real License Hub instance on a temp port + sqlite DB before
 * invoking this file):
 *   E2E_LICENSE_HUB_URL     e.g. http://127.0.0.1:8091
 *   E2E_LICENSE_HUB_REPO    absolute path to the license-hub checkout,
 *                           used to shell out to its scripts/e2e_seed.php
 *                           and scripts/e2e_action.php for fixtures
 *
 * Run with:
 *   E2E_LICENSE_HUB_URL=http://127.0.0.1:8091 \
 *   E2E_LICENSE_HUB_REPO=/path/to/license-hub \
 *   php artisan test tests/E2E/RealLicenseHubHandshakeTest.php
 *
 * (scripts/e2e/run.sh does exactly this, plus starting/stopping the Hub
 * server and picking a free port.)
 */
final class RealLicenseHubHandshakeTest extends TestCase
{
    use RefreshDatabase;

    private const SIGNING_KEY_ID = 'e2e-key';
    private const SIGNING_SECRET = 'e2e-secret-do-not-use-in-production-0123456789';

    private string $hubUrl;
    private string $hubRepo;
    private string $hubDbConnection;
    private string $hubDbDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $hubUrl = getenv('E2E_LICENSE_HUB_URL');
        $hubRepo = getenv('E2E_LICENSE_HUB_REPO');
        $hubDbDatabase = getenv('E2E_LICENSE_HUB_DB_DATABASE');

        if ($hubUrl === false || $hubRepo === false || $hubDbDatabase === false) {
            self::markTestSkipped(
                'RealLicenseHubHandshakeTest requires E2E_LICENSE_HUB_URL, E2E_LICENSE_HUB_REPO and '
                .'E2E_LICENSE_HUB_DB_DATABASE (a live License Hub instance and the exact DB file it reads '
                .'from, so this test\'s seed/action scripts write to the same database the running server '
                .'sees). Run via scripts/e2e/run.sh, not directly.'
            );
        }

        $this->hubUrl = $hubUrl;
        $this->hubRepo = $hubRepo;
        $this->hubDbConnection = getenv('E2E_LICENSE_HUB_DB_CONNECTION') ?: 'sqlite';
        $this->hubDbDatabase = $hubDbDatabase;

        config([
            'commerce-hub.license_hub.url' => $this->hubUrl,
            'commerce-hub.license_hub.key_id' => self::SIGNING_KEY_ID,
            'commerce-hub.license_hub.secret' => self::SIGNING_SECRET,
            'commerce-hub.license_hub.timeout' => 5,
            'commerce-hub.license_hub.enforce_gating' => true,
        ]);
    }

    /** Shells out to the real License Hub app to seed/mutate fixtures -- see class docblock. */
    private function hub(string $scriptAndArgs): array
    {
        // The env-var prefix must sit directly before the `php` invocation
        // -- `VAR=val cmd1 && cmd2` only applies VAR to cmd1, not cmd2, so
        // it cannot be prepended to the whole `cd ... && php ...` string.
        $cmd = sprintf(
            'cd %s && DOSIECI_SIGNING_KEY_ID=%s DOSIECI_SIGNING_SECRET=%s DB_CONNECTION=%s DB_DATABASE=%s php %s 2>&1',
            escapeshellarg($this->hubRepo),
            escapeshellarg(self::SIGNING_KEY_ID),
            escapeshellarg(self::SIGNING_SECRET),
            escapeshellarg($this->hubDbConnection),
            escapeshellarg($this->hubDbDatabase),
            $scriptAndArgs
        );
        $output = shell_exec($cmd);
        self::assertIsString($output, 'License Hub helper script produced no output: '.$scriptAndArgs);

        $lastLine = trim(strrchr(trim($output), "\n") ?: $output);
        $decoded = json_decode($lastLine, true);
        self::assertIsArray($decoded, "License Hub helper script did not return JSON.\nCommand: {$scriptAndArgs}\nOutput: {$output}");

        return $decoded;
    }

    private function seedWorkspace(): array
    {
        return $this->hub('scripts/e2e_seed.php');
    }

    private function issueToken(string $workspaceId, string $product = 'elinker', int $ttl = 900): string
    {
        return $this->hub(sprintf(
            'scripts/e2e_action.php issue-token %s %s %d',
            escapeshellarg($workspaceId),
            escapeshellarg($product),
            $ttl
        ))['token'];
    }

    private function setWorkspaceStatus(string $workspaceId, string $status): void
    {
        $this->hub(sprintf(
            'scripts/e2e_action.php set-status %s %s',
            escapeshellarg($workspaceId),
            escapeshellarg($status)
        ));
    }

    private function makeCompanyAndAdmin(string $suffix): array
    {
        $company = Company::create(['name' => 'E2E '.$suffix, 'email' => "e2e-{$suffix}@example.test"]);
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);

        return [$company, $user];
    }

    /**
     * Scenarios 1-6: consume, save workspace_id, immediate check, active/
     * plan_code/features all saved -- all over a real socket to a real
     * License Hub process.
     */
    public function test_1_to_6_connect_saves_workspace_id_and_refreshes_real_entitlement(): void
    {
        $fixture = $this->seedWorkspace();
        [$company, $user] = $this->makeCompanyAndAdmin('connect');

        $response = $this->actingAs($user)->post('/settings/billing/connect', ['token' => $fixture['token']]);
        $response->assertRedirect();

        $fresh = $company->fresh();
        self::assertSame($fixture['workspace_id'], $fresh->license_hub_workspace_id, 'workspace_id must be the one the real Hub resolved, not a guess');
        self::assertSame('active', $fresh->entitlement_status);
        self::assertSame('e2e_pro', $fresh->entitlement_plan_code);
        self::assertIsArray($fresh->entitlement_features);
        self::assertTrue($fresh->entitlement_features['elinker.sync']['enabled'] ?? null);
        self::assertSame(3, $fresh->entitlement_features['elinker.channels.woocommerce']['limit'] ?? null);
    }

    /** Scenario 7: token replay is rejected by the real Hub. */
    public function test_7_token_replay_is_rejected(): void
    {
        $fixture = $this->seedWorkspace();
        [$company1, $user1] = $this->makeCompanyAndAdmin('replay1');
        [$company2, $user2] = $this->makeCompanyAndAdmin('replay2');

        $first = $this->actingAs($user1)->post('/settings/billing/connect', ['token' => $fixture['token']]);
        $first->assertRedirect();
        self::assertSame($fixture['workspace_id'], $company1->fresh()->license_hub_workspace_id);

        $replay = $this->actingAs($user2)->post('/settings/billing/connect', ['token' => $fixture['token']]);
        $replay->assertSessionHasErrors('token');
        self::assertNull($company2->fresh()->license_hub_workspace_id, 'a replayed token must not link a second company');
    }

    public function test_token_for_a_different_product_is_rejected_by_the_real_hub(): void
    {
        $fixture = $this->seedWorkspace();
        $wrongProductToken = $this->issueToken($fixture['workspace_id'], 'some-other-product');

        $client = new LicenseHubClient();
        $this->expectException(ProductLinkRejectedException::class);
        $client->consumeProductLink($wrongProductToken);
    }

    public function test_an_expired_token_is_rejected_by_the_real_hub(): void
    {
        $fixture = $this->seedWorkspace();
        $expiredToken = $this->issueToken($fixture['workspace_id'], 'elinker', -5);

        $client = new LicenseHubClient();
        $this->expectException(ProductLinkRejectedException::class);
        $client->consumeProductLink($expiredToken);
    }

    /** Scenario 8: an invalid HMAC signature is rejected by the real Hub (a genuine 401 over the wire, not a mocked assertion). */
    public function test_8_invalid_hmac_is_rejected_by_the_real_hub(): void
    {
        $fixture = $this->seedWorkspace();
        $body = json_encode(['product' => 'elinker', 'token' => $fixture['token']]);

        $wrongSignatureHeaders = (new LicenseHubRequestSigner())->sign(
            'POST',
            '/api/v1/product-links/consume',
            $body,
            self::SIGNING_KEY_ID,
            'a-completely-wrong-secret'
        );

        $response = Http::baseUrl($this->hubUrl)
            ->withHeaders($wrongSignatureHeaders)
            ->withBody($body, 'application/json')
            ->post('/api/v1/product-links/consume');

        self::assertSame(401, $response->status());
    }

    /**
     * Scenario 9: a real, unreachable-Hub timeout must never flip an
     * already-active company to suspended -- only sync_status degrades.
     */
    public function test_9_a_real_hub_timeout_does_not_suspend_the_company(): void
    {
        $fixture = $this->seedWorkspace();
        [$company, $user] = $this->makeCompanyAndAdmin('outage');
        $this->actingAs($user)->post('/settings/billing/connect', ['token' => $fixture['token']])->assertRedirect();
        self::assertSame('active', $company->fresh()->entitlement_status);

        // 127.0.0.1:1 is a real address nothing listens on -- a genuine
        // connection failure, not a simulated one.
        config(['commerce-hub.license_hub.url' => 'http://127.0.0.1:1', 'commerce-hub.license_hub.timeout' => 2]);

        app(SubscriptionEntitlementService::class)->refresh($company->fresh());

        $afterOutage = $company->fresh();
        self::assertSame('active', $afterOutage->entitlement_status, 'a Hub outage must never flip active to suspended');
        self::assertSame('e2e_pro', $afterOutage->entitlement_plan_code);
        self::assertSame('degraded', $afterOutage->entitlement_sync_status);
    }

    /**
     * Scenario 10+11: after a successful connect, a real "suspended"
     * status from the Hub (following a refresh) blocks a gated action --
     * and the company can still read its own orders/billing state.
     */
    public function test_10_and_11_a_real_suspended_status_blocks_gating_but_not_read_access(): void
    {
        $fixture = $this->seedWorkspace();
        [$company, $user] = $this->makeCompanyAndAdmin('suspend');
        $this->actingAs($user)->post('/settings/billing/connect', ['token' => $fixture['token']])->assertRedirect();
        self::assertSame('active', $company->fresh()->entitlement_status);

        $channel = $company->salesChannels()->create(['type' => 'woocommerce', 'name' => 'Sklep', 'status' => 'active']);
        $order = $company->orders()->create([
            'sales_channel_id' => $channel->id,
            'external_order_id' => 'e2e-order-1',
            'source' => 'woocommerce',
            'total' => 10,
            'currency' => 'PLN',
        ]);

        $this->setWorkspaceStatus($fixture['workspace_id'], 'suspended');
        app(SubscriptionEntitlementService::class)->refresh($company->fresh());
        self::assertSame('suspended', $company->fresh()->entitlement_status);

        // (10) gated action blocked
        $blocked = $this->actingAs($user)->post('/channels/woocommerce', [
            'name' => 'Sklep 2', 'base_url' => 'https://shop.example.test',
            'consumer_key' => 'ck', 'consumer_secret' => 'cs',
        ]);
        $blocked->assertRedirect();
        $blocked->assertSessionHas('error');

        // (11) read-only access still works, and nothing was deleted
        $this->actingAs($user)->get('/')->assertOk();
        $this->actingAs($user)->get('/orders')->assertOk();
        $this->actingAs($user)->get('/settings/billing')->assertOk();
        self::assertDatabaseHas('sales_channels', ['id' => $channel->id]);
        self::assertDatabaseHas('commerce_orders', ['id' => $order->id]);
    }
}
