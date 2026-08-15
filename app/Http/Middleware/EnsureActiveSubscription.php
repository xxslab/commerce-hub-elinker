<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\Licensing\SubscriptionEntitlementService;
use Closure;
use Illuminate\Http\Request;

/**
 * Blocks actions that require an active subscription (creating a new sales
 * channel, starting a new sync, creating a new shipment). Deliberately
 * NEVER applied to: login, dashboard, /orders (view), /settings/billing,
 * logout, or anything the user needs to see their own suspended state and
 * get to a resolution — see BILLING_ARCHITECTURE.md (License Hub repo)
 * "account_suspended" semantics, which this middleware mirrors on the
 * eLinker side. A suspended company never has its data touched; this only
 * blocks new gated actions.
 */
class EnsureActiveSubscription
{
    public function __construct(private SubscriptionEntitlementService $entitlements)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user || !$user->company_id) {
            return $next($request);
        }

        $company = Company::find($user->company_id);
        if (!$company || $this->entitlements->isActive($company)) {
            return $next($request);
        }

        $message = 'Ta funkcja wymaga aktywnego abonamentu. Sprawdź stan konta w Ustawienia → Plan i billing.';

        if ($request->expectsJson()) {
            return response()->json(['error' => $message], 403);
        }

        return back()->with('error', $message);
    }
}
