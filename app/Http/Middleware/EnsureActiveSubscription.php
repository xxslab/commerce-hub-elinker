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
 *
 * An optional route parameter names a SubscriptionEntitlementService
 * feature key (see FeatureKeys) to additionally enforce via can(), e.g.
 * `subscription.active:elinker.channels.woocommerce`. This is the single
 * enforcement point for both checks so gating logic doesn't get scattered
 * across controllers — a controller never calls the entitlement service
 * itself for this purpose.
 *
 * Deliberately NOT wired onto any route yet: can() default-denies an
 * unmapped feature key, and every plan ships with zero plan_features rows
 * today (see License Hub's PlanController docblock — no business data to
 * seed them with exists anywhere in either repo). Passing a feature key
 * here before the plan catalog is populated would turn ENFORCE_GATING=true
 * into "block every active company from every gated action", not
 * per-plan limits. Wire a key onto a route only once the corresponding
 * plan_features rows actually exist in production.
 */
class EnsureActiveSubscription
{
    public function __construct(private SubscriptionEntitlementService $entitlements)
    {
    }

    public function handle(Request $request, Closure $next, ?string $featureKey = null)
    {
        $user = $request->user();
        if (!$user || !$user->company_id) {
            return $next($request);
        }

        $company = Company::find($user->company_id);
        if (!$company) {
            return $next($request);
        }

        if (!$this->entitlements->isActive($company)) {
            return $this->blocked($request, 'Ta funkcja wymaga aktywnego abonamentu. Sprawdź stan konta w Ustawienia → Plan i billing.');
        }

        if ($featureKey !== null && !$this->entitlements->can($company, $featureKey)) {
            return $this->blocked($request, 'Ta funkcja nie jest dostępna w Twoim obecnym planie. Sprawdź Ustawienia → Plan i billing.');
        }

        return $next($request);
    }

    private function blocked(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $message], 403);
        }

        return back()->with('error', $message);
    }
}
