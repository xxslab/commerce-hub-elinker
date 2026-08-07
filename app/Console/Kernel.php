<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('commerce-hub:sync-orders')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('commerce-hub:refresh-marketplace-tokens')->hourly()->withoutOverlapping();
        $schedule->command('commerce-hub:sync-tracking')->everyFifteenMinutes()->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
