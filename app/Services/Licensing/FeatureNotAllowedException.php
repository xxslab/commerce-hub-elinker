<?php

namespace App\Services\Licensing;

/**
 * Raised by SubscriptionEntitlementService::assertAllowed() when a gated
 * action is not covered by the company's current plan (feature disabled,
 * limit reached, or feature key unmapped for that plan — all three are
 * "not allowed", per the default-deny policy documented on can()).
 */
class FeatureNotAllowedException extends \RuntimeException
{
}
