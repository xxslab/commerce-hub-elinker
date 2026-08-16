<?php

namespace App\Services\Licensing;

/**
 * The set of eLinker feature keys License Hub plan_features rows may use
 * (plan_features.key on the Hub side must match these exactly). These are
 * NOT invented limits or prices — see PlanController's docblock in the
 * License Hub repo: no plan actually has these rows populated yet, so
 * every one of these keys currently resolves to "not entitled" via
 * SubscriptionEntitlementService's default-deny behavior until a License
 * Hub admin configures a real plan catalog.
 */
final class FeatureKeys
{
    public const CHANNEL_WOOCOMMERCE = 'elinker.channels.woocommerce';
    public const CHANNEL_ALLEGRO = 'elinker.channels.allegro';
    public const CHANNEL_EBAY = 'elinker.channels.ebay';
    public const CHANNELS_TOTAL = 'elinker.channels.total';
    public const SHIPMENTS_INPOST = 'elinker.shipments.inpost';
    public const SYNC = 'elinker.sync';
    public const USERS = 'elinker.users';

    private function __construct()
    {
    }
}
