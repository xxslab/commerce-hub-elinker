<?php

namespace App\Console\Commands;

use App\Jobs\RefreshEntitlementJob;
use App\Models\Company;
use Illuminate\Console\Command;

class RefreshEntitlementsCommand extends Command
{
    protected $signature = 'commerce-hub:sync-entitlement';
    protected $description = 'Refresh the local License Hub entitlement projection for every linked company.';

    public function handle(): int
    {
        $companies = Company::query()->whereNotNull('license_hub_workspace_id')->get();

        foreach ($companies as $company) {
            RefreshEntitlementJob::dispatch($company->id);
            $this->line("Queued entitlement refresh for company: {$company->name}");
        }

        return self::SUCCESS;
    }
}
