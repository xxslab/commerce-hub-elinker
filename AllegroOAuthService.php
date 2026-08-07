<?php

namespace App\Services\Integrations\Allegro;

use App\Models\Company;
use App\Models\MarketplaceAppCredential;
use App\Models\SalesChannel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AllegroOAuthService
{
    public function buildAuthorizationUrl(Company $company): string
    {
        $app = $this->activeApp($company->id);
        $state = $this->makeState($company->id, $app->id);

        return $this->authUrl($app) . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $app->client_id,
            'redirect_uri' => $app->redirect_uri,
            'state' => $state,
        ]);
    }

    public function handleCallback(string $code, string $state): SalesChannel
    {
        $statePayload = $this->readState($state);
        $companyId = (int) $statePayload['company_id'];
        $app = MarketplaceAppCredential::query()->findOrFail((int) $statePayload['app_id']);
        abort_unless((int) $app->company_id === $companyId, 403);

        $response = Http::asForm()
            ->withBasicAuth($app->client_id, (string) $app->getClientSecret())
            ->post($this->tokenUrl($app), [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $app->redirect_uri,
            ]);

        $response->throw();
        $token = $response->json();

        $channel = SalesChannel::create([
            'company_id' => $companyId,
            'type' => SalesChannel::TYPE_ALLEGRO,
            'name' => 'Allegro ' . now()->format('Y-m-d H:i'),
            'base_url' => null,
            'status' => 'active',
            'settings' => [
                'marketplace_app_credential_id' => $app->id,
                'environment' => $app->environment,
                'connected_at' => now()->toIso8601String(),
                'expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 43200))->toIso8601String(),
            ],
            'last_token_refresh_at' => now(),
        ]);

        $channel->setCredentials([
            'access_token' => $token['access_token'] ?? null,
            'refresh_token' => $token['refresh_token'] ?? null,
            'token_type' => $token['token_type'] ?? 'bearer',
            'expires_in' => $token['expires_in'] ?? null,
            'scope' => $token['scope'] ?? null,
        ]);
        $channel->save();

        return $channel;
    }

    public function refreshToken(SalesChannel $channel): SalesChannel
    {
        $app = $this->appForChannel($channel);
        $credentials = $channel->getCredentials();
        $refreshToken = $credentials['refresh_token'] ?? null;

        if (!$refreshToken) {
            throw new \RuntimeException('Brak refresh_token dla kanału Allegro. Połącz konto ponownie.');
        }

        $response = Http::asForm()
            ->withBasicAuth($app->client_id, (string) $app->getClientSecret())
            ->post($this->tokenUrl($app), [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'redirect_uri' => $app->redirect_uri,
            ]);

        $response->throw();
        $token = $response->json();

        $channel->setCredentials(array_merge($credentials, [
            'access_token' => $token['access_token'] ?? $credentials['access_token'] ?? null,
            'refresh_token' => $token['refresh_token'] ?? $refreshToken,
            'token_type' => $token['token_type'] ?? ($credentials['token_type'] ?? 'bearer'),
            'expires_in' => $token['expires_in'] ?? null,
            'scope' => $token['scope'] ?? ($credentials['scope'] ?? null),
        ]));

        $settings = $channel->settings ?: [];
        $settings['marketplace_app_credential_id'] = $app->id;
        $settings['expires_at'] = now()->addSeconds((int) ($token['expires_in'] ?? 43200))->toIso8601String();
        $settings['token_refreshed_at'] = now()->toIso8601String();

        $channel->forceFill([
            'settings' => $settings,
            'last_token_refresh_at' => now(),
        ])->save();

        return $channel;
    }

    private function activeApp(int $companyId): MarketplaceAppCredential
    {
        $app = MarketplaceAppCredential::query()->forCompanyMarketplace($companyId, MarketplaceAppCredential::MARKETPLACE_ALLEGRO)->first();

        if (!$app) {
            throw new \RuntimeException('Najpierw uzupełnij dane aplikacji Allegro w panelu: Ustawienia marketplace.');
        }

        return $app;
    }

    private function appForChannel(SalesChannel $channel): MarketplaceAppCredential
    {
        $settings = $channel->settings ?: [];
        if (!empty($settings['marketplace_app_credential_id'])) {
            return MarketplaceAppCredential::query()->findOrFail((int) $settings['marketplace_app_credential_id']);
        }

        return $this->activeApp((int) $channel->company_id);
    }

    private function authUrl(MarketplaceAppCredential $app): string
    {
        return $app->environment === 'sandbox'
            ? 'https://allegro.pl.allegrosandbox.pl/auth/oauth/authorize'
            : 'https://allegro.pl/auth/oauth/authorize';
    }

    private function tokenUrl(MarketplaceAppCredential $app): string
    {
        return $app->environment === 'sandbox'
            ? 'https://allegro.pl.allegrosandbox.pl/auth/oauth/token'
            : 'https://allegro.pl/auth/oauth/token';
    }

    private function makeState(int $companyId, int $appId): string
    {
        $nonce = Str::random(32);
        session()->put('commerce_hub.oauth_nonce.' . $nonce, $companyId . ':' . $appId);
        return Crypt::encryptString(json_encode([
            'company_id' => $companyId,
            'app_id' => $appId,
            'nonce' => $nonce,
            'issued_at' => now()->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    private function readState(string $state): array
    {
        $payload = json_decode(Crypt::decryptString($state), true, 512, JSON_THROW_ON_ERROR);
        abort_unless(isset($payload['issued_at']) && (int) $payload['issued_at'] >= now()->subMinutes(10)->timestamp, 419);
        abort_unless(!empty($payload['nonce']), 419);
        abort_unless(session()->pull('commerce_hub.oauth_nonce.' . $payload['nonce']) === ((int) $payload['company_id'] . ':' . (int) $payload['app_id']), 419);
        return $payload;
    }
}
