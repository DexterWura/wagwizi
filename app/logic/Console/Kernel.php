<?php

namespace App\Console;

use App\Jobs\RefreshExpiredTokensJob;
use App\Services\SocialAccount\TokenRefreshService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Fallback scheduler for environments using `php artisan schedule:run`.
     * In production use GET or POST /cron?token=… (cPanel-friendly) or POST /api/cron/run with X-Cron-Secret;
     * with task enablement and intervals managed by admins via the cron_tasks table.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('posts:publish-due')->everyMinute();

        $schedule->job(new RefreshExpiredTokensJob)->everyFifteenMinutes();

        $schedule->call(function () {
            $n = app(TokenRefreshService::class)->refreshAccountsWithUnknownExpiry();
            \Illuminate\Support\Facades\Log::info('refreshAccountsWithUnknownExpiry', ['refreshed' => $n]);
        })->dailyAt('04:15');

        $schedule->command('logs:purge --days=7')->dailyAt('03:00');

        $schedule->command('notifications:send-expiry-reminders')->dailyAt('08:00');
    }

    protected $commands = [
        Commands\PublishDuePosts::class,
        Commands\PurgeOldLogs::class,
        Commands\SeedCronTasks::class,
        Commands\SendInAppExpiryRemindersCommand::class,
        Commands\MinifyAssets::class,
    ];

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
    }
}
