<?php

namespace App\Services\Licensing;

use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP adapter for License Hub's entitlement-check endpoint
 * (POST /api/v1/entitlements/check, signed). Deliberately dumb — no
 * caching, no gating decisions, no writes to Company — that belongs in
 * SubscriptionEntitlementService, which is the only caller of this class.
 */
class LicenseHubClient
{
    public function __construct(private LicenseHubRequestSigner $signer = new LicenseHubRequestSigner())
    {
    }

    /**
     * @throws LicenseHubUnavailableException on timeout, connection failure,
     *         non-2xx response, or a 2xx response that isn't valid JSON —
     *         callers must treat all of these as "degraded", never as
     *         "not entitled" (see SubscriptionEntitlementService::refresh()).
     */
    public function checkEntitlement(string $workspaceId): array
    {
        $keyId = (string) config('commerce-hub.license_hub.key_id');
        $secret = (string) config('commerce-hub.license_hub.secret');

        if ($keyId === '' || $secret === '') {
            throw new LicenseHubUnavailableException('License Hub signing key is not configured (LICENSE_HUB_KEY_ID/LICENSE_HUB_SECRET).');
        }

        $path = '/api/v1/entitlements/check';
        $payload = ['workspace_id' => $workspaceId];
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        $headers = $this->signer->sign('POST', $path, $body, $keyId, $secret);

        try {
            $response = Http::baseUrl((string) config('commerce-hub.license_hub.url'))
                ->timeout((int) config('commerce-hub.license_hub.timeout', 10))
                ->withHeaders($headers)
                ->withBody($body, 'application/json')
                ->post($path);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new LicenseHubUnavailableException('Could not connect to License Hub: ' . $e->getMessage(), 0, $e);
        }

        if (!$response->successful()) {
            throw new LicenseHubUnavailableException('License Hub returned HTTP ' . $response->status() . '.');
        }

        $data = $response->json();
        if (!is_array($data) || !array_key_exists('active', $data)) {
            throw new LicenseHubUnavailableException('License Hub returned a malformed entitlement response.');
        }

        return $data;
    }
}
