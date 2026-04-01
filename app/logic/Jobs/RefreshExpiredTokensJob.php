<?php

namespace App\Jobs;

use App\Services\SocialAccount\TokenRefreshService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
}
