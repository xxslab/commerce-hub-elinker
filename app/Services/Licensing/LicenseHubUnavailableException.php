<?php

namespace App\Services\Licensing;

/**
 * Raised for any failure talking to License Hub — timeout, connection
 * failure, non-2xx, or malformed JSON. Deliberately a single exception
 * type: SubscriptionEntitlementService treats every one of these cases
 * identically (mark sync degraded, never touch the locally-cached
 * status/plan_code) — see its class docblock for why that's the
 * "WHMCS-down" guarantee applied on this side of the wire too.
 */
class LicenseHubUnavailableException extends \RuntimeException
{
}
