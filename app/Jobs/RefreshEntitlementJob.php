<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\Licensing\SubscriptionEntitlementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshEntitlementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900];

    public function __construct(public int $companyId)
    {
    }

    public function handle(SubscriptionEntitlementService $service): void
    {
        $company = Company::find($this->companyId);
        if (!$company) {
            return;
        }

        $service->refresh($company);
    }
}
