<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Stable link to a License Hub workspace_id. Deliberately not the
            // company's e-mail or any WHMCS identifier — see
            // BILLING_ARCHITECTURE.md in the License Hub repo, "external_service_id
            // — never email — is the stable billing identifier". Null means
            // "not linked yet"; gating stays inactive for such companies (see
            // SubscriptionEntitlementService::isGatingApplicable()).
            $table->string('license_hub_workspace_id')->nullable()->unique();

            // Local, fast-read projection of the last entitlement check —
            // mirrors the same "never call the billing backend on every
            // request" pattern License Hub itself uses for WHMCS
            // (BillingAccount). entitlement_status/entitlement_plan_code are
            // only ever written by a SUCCESSFUL check; a failed/timed-out
            // check only touches entitlement_sync_status, never these two —
            // see SubscriptionEntitlementService::refresh().
            $table->string('entitlement_status')->nullable();
            $table->string('entitlement_plan_code')->nullable();
            $table->json('entitlement_features')->nullable();
            $table->timestamp('entitlement_checked_at')->nullable();
            $table->string('entitlement_sync_status')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'license_hub_workspace_id',
                'entitlement_status',
                'entitlement_plan_code',
                'entitlement_features',
                'entitlement_checked_at',
                'entitlement_sync_status',
            ]);
        });
    }
};
