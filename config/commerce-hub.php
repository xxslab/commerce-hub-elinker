<?php

return [
    'allow_registration' => env('COMMERCE_HUB_ALLOW_REGISTRATION', false),
    'sync' => [
        'orders_interval_minutes' => env('CH_ORDERS_SYNC_INTERVAL', 5),
        'tracking_interval_minutes' => env('CH_TRACKING_SYNC_INTERVAL', 15),
    ],

    'allegro' => [
        'client_id' => env('ALLEGRO_CLIENT_ID'),
        'client_secret' => env('ALLEGRO_CLIENT_SECRET'),
        'redirect_uri' => env('ALLEGRO_REDIRECT_URI'),
        'auth_url' => env('ALLEGRO_AUTH_URL', 'https://allegro.pl/auth/oauth/authorize'),
        'token_url' => env('ALLEGRO_TOKEN_URL', 'https://allegro.pl/auth/oauth/token'),
        'api_url' => env('ALLEGRO_API_URL', 'https://api.allegro.pl'),
    ],

    'ebay' => [
        'client_id' => env('EBAY_CLIENT_ID'),
        'client_secret' => env('EBAY_CLIENT_SECRET'),
        'redirect_uri' => env('EBAY_REDIRECT_URI'),
        'scopes' => array_filter(explode(' ', env('EBAY_SCOPES', 'https://api.ebay.com/oauth/api_scope/sell.fulfillment.readonly https://api.ebay.com/oauth/api_scope/sell.fulfillment'))),
        'auth_url' => env('EBAY_AUTH_URL', 'https://auth.ebay.com/oauth2/authorize'),
        'token_url' => env('EBAY_TOKEN_URL', 'https://api.ebay.com/identity/v1/oauth2/token'),
        'api_url' => env('EBAY_API_URL', 'https://api.ebay.com'),
        // eBay's shippingCarrierCode enum is marketplace-specific (see
        // GeteBayDetails/ShippingCarrierDetails in the Trading API) and not
        // resolvable from the Fulfillment API alone. "Other" is the
        // documented generic fallback; override once the exact code for the
        // target marketplace is confirmed live.
        'carrier_codes' => [
            'inpost' => env('EBAY_CARRIER_CODE_INPOST', 'Other'),
        ],
    ],

    'inpost' => [
        'api_url' => env('INPOST_API_URL', 'https://api-shipx-pl.easypack24.net'),
        'token' => env('INPOST_API_TOKEN'),
        'organization_id' => env('INPOST_ORGANIZATION_ID'),
    ],

    // License Hub (license.dosieci.pl) entitlement integration. Uses the
    // same DoSieci request-signing protocol (X-DoSieci-Key-Id/Timestamp/
    // Nonce/Signature/Signature-Version, HMAC-SHA256 over
    // METHOD\nPATH\nTIMESTAMP\nNONCE\nSHA256(BODY)) that WHMCS/storefront
    // connectors use against the Hub — see
    // App\Services\Licensing\LicenseHubRequestSigner.
    'license_hub' => [
        'url' => env('LICENSE_HUB_URL', 'https://license.dosieci.pl'),
        'key_id' => env('LICENSE_HUB_KEY_ID'),
        'secret' => env('LICENSE_HUB_SECRET'),
        'timeout' => env('LICENSE_HUB_TIMEOUT', 10),

        // How often the scheduled entitlement refresh re-checks each linked
        // company (commerce-hub:sync-entitlement). Explicit and
        // configurable per CLAUDE.md rule against unstated defaults.
        'refresh_interval_minutes' => env('LICENSE_HUB_REFRESH_INTERVAL', 60),

        // How long a local entitlement snapshot is trusted after a failed/
        // degraded refresh before gating falls back to a restrictive
        // decision for a SUSPENDED-at-last-known-good-state company. This is
        // deliberately NOT "how long until we assume suspended" — a
        // License Hub outage must never itself suspend an active company
        // (see SubscriptionEntitlementService::isActive()) — it only bounds
        // how stale a *already-suspended* reading may be trusted, so a
        // fixed briefly-down Hub cannot indefinitely freeze a company that
        // was suspended right before the outage into "still active" either.
        'grace_period_minutes' => env('LICENSE_HUB_GRACE_PERIOD', 720),

        // Master switch for feature gating. Off by default: gating a
        // production app on a billing integration that has no real plan
        // catalog seeded yet and no companies linked would lock everyone
        // out the moment this ships. Turn on only after companies are
        // actually linked (license_hub_workspace_id set) and plans exist.
        'enforce_gating' => env('LICENSE_HUB_ENFORCE_GATING', false),
    ],
];
