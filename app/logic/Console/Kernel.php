<?php

namespace App\Console;

use App\Jobs\RefreshExpiredTokensJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Fallback scheduler for environments using `php artisan schedule:run`.
     * In production the single /api/cron/run endpoint is the preferred trigger,
     * with task enablement and intervals managed by admins via the cron_tasks table.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('posts:publish-due')->everyMinute();

        $schedule->job(new RefreshExpiredTokensJob)->everyFifteenMinutes();

        $schedule->command('logs:purge --days=14')->dailyAt('03:00');

        $schedule->command('notifications:send-expiry-reminders')->dailyAt('08:00');
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
    }
}
