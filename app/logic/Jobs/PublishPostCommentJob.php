<?php

namespace App\Jobs;

use App\Models\PostPlatform;
use App\Services\Notifications\InAppNotificationService;
use App\Services\Platform\Platform;
use App\Services\Platform\PlatformRegistry;
use App\Services\SocialAccount\TokenRefreshService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishPostCommentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        private readonly int $postPlatformId,
    ) {}

    public function handle(
        PlatformRegistry $registry,
        TokenRefreshService $tokenRefreshService,
    ): void {
        $postPlatform = PostPlatform::with('socialAccount')->find($this->postPlatformId);

        if ($postPlatform === null || $postPlatform->status !== 'published') {
            return;
        }

        $comment = trim((string) ($postPlatform->first_comment ?? ''));
        if ($comment === '' || empty($postPlatform->platform_post_id)) {
            $postPlatform->update([
                'comment_status' => 'failed',
                'comment_error_message' => 'Missing comment text or target post id.',
            ]);
            return;
        }

        $account = $postPlatform->socialAccount;
        if ($account === null || $account->status !== 'active') {
            $postPlatform->update([
                'comment_status' => 'failed',
                'comment_error_message' => 'Account unavailable or inactive.',
            ]);
            return;
        }

        $platform = Platform::tryFrom($postPlatform->platform);
        if ($platform === null) {
            $postPlatform->update([
                'comment_status' => 'failed',
                'comment_error_message' => 'Unknown platform.',
            ]);
            return;
        }

        $tokenRefreshService->refreshIfNeeded($account);
        $account->refresh();

        if ($account->isTokenExpired()) {
            $postPlatform->update([
                'comment_status' => 'failed',
                'comment_error_message' => 'Access token expired and refresh failed. Reconnect account.',
            ]);
            return;
        }

        try {
            $adapter = $registry->resolve($platform);
            $ok = $adapter->publishComment($account, $postPlatform->platform_post_id, $comment);

            if (!$ok) {
                $postPlatform->update([
                    'comment_status' => 'failed',
                    'comment_error_message' => 'Comment publishing unsupported or failed on platform.',
                ]);
                Log::info('Platform does not support first-comment publishing', [
                    'post_platform_id' => $postPlatform->id,
                    'platform' => $postPlatform->platform,
                ]);
                return;
            }

            $postPlatform->update([
                'comment_status' => 'published',
                'comment_error_message' => null,
                'comment_published_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $postPlatform->update([
                'comment_status' => 'failed',
                'comment_error_message' => mb_substr($e->getMessage(), 0, 1000),
            ]);
            Log::warning('Failed to publish follow-up comment', [
                'post_platform_id' => $postPlatform->id,
                'platform' => $postPlatform->platform,
                'message' => $e->getMessage(),
            ]);

            try {
                app(InAppNotificationService::class)->notifySuperAdminsOperationalAlert(
                    'admin_critical_post_comment',
                    'First comment failed to publish',
                    ucfirst((string) $postPlatform->platform) . ", post_platform #{$postPlatform->id}: " . mb_substr($e->getMessage(), 0, 400),
                    route('admin.operations'),
                    ['post_platform_id' => $postPlatform->id],
                    'post_comment_fail:' . $postPlatform->id,
                    900,
                );
            } catch (\Throwable) {
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        try {
            app(InAppNotificationService::class)->notifySuperAdminsOperationalAlert(
                'admin_critical_post_comment',
                'Comment job crashed',
                'post_platform #' . $this->postPlatformId . ': ' . mb_substr($exception->getMessage(), 0, 400),
                route('admin.operations'),
                ['post_platform_id' => $this->postPlatformId],
                'post_comment_job_crash:' . $this->postPlatformId,
                1800,
            );
        } catch (\Throwable) {
        }
    }
}

