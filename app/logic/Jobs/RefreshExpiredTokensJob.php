<?php

namespace App\Jobs;

use App\Services\Notifications\InAppNotificationService;
use App\Services\SocialAccount\TokenRefreshService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefreshExpiredTokensJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function handle(TokenRefreshService $tokenRefreshService): void
    {
        $refreshed = $tokenRefreshService->refreshExpiringSoon();

        Log::info('Token refresh job completed', [
            'tokens_refreshed' => $refreshed,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        try {
            app(InAppNotificationService::class)->notifySuperAdminsOperationalAlert(
                'admin_critical_token_refresh',
                'Token refresh job failed',
                'Scheduled social token refresh crashed: ' . mb_substr($exception->getMessage(), 0, 400),
                route('admin.operations'),
                [],
                'token_refresh_job_fail:' . md5($exception->getMessage()),
                7200,
            );
        } catch (Throwable) {
        }
    }
}
