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
    ],

    'inpost' => [
        'api_url' => env('INPOST_API_URL', 'https://api-shipx-pl.easypack24.net'),
        'token' => env('INPOST_API_TOKEN'),
        'organization_id' => env('INPOST_ORGANIZATION_ID'),
    ],
];
