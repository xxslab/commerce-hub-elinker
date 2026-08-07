<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\SyncAllSalesChannelsCommand::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('commerce-hub:sync-orders')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('queue:work --stop-when-empty --tries=3 --timeout=120')->everyMinute()->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
