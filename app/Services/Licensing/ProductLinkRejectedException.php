<?php

namespace App\Services\Licensing;

/**
 * Raised when License Hub's POST /api/v1/product-links/consume rejects a
 * connection code with a definitive 422 (unknown/expired/already-used/
 * wrong-product token). Deliberately distinct from
 * LicenseHubUnavailableException: this is a legitimate, user-facing
 * rejection of the code the admin typed in, not a Hub outage — it must be
 * shown to the admin as "this code is invalid", never treated as
 * "degraded sync" and never silently retried.
 */
class ProductLinkRejectedException extends \RuntimeException
{
}
