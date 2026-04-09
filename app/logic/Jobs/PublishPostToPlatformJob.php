<?php

namespace App\Jobs;

use App\Jobs\PublishPostCommentJob;
use App\Models\PostPlatform;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\SiteSetting;
use App\Services\Cache\UserCacheVersionService;
use App\Services\Platform\Platform;
use App\Services\Platform\PlatformRegistry;
use App\Services\Notifications\InAppNotificationService;
use App\Services\Post\MediaPublishPreflightService;
use App\Services\Post\PostPublishingService;
use App\Services\Post\PublishErrorClassifier;
use App\Services\Post\PublishExecutionMode;
use App\Services\SocialAccount\TokenRefreshService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PublishPostToPlatformJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 90;

    public function __construct(
        private readonly int $postPlatformId,
    ) {}

    public function handle(
        PlatformRegistry    $registry,
        TokenRefreshService $tokenRefreshService,
        PostPublishingService $postPublishingService,
    ): void {
        $platformLock = Cache::lock('publish_post_platform:' . $this->postPlatformId, 180);
        if (! $platformLock->get()) {
            $this->release(10);
            return;
        }

        try {
            if ($this->skipIfAlreadyPublished($postPublishingService)) {
                return;
            }

            $postPlatform = PostPlatform::with(['post', 'socialAccount'])->find($this->postPlatformId);

            if ($postPlatform === null) {
                Log::warning('PublishPostToPlatformJob: PostPlatform row not found', [
                    'post_platform_id' => $this->postPlatformId,
                ]);
                return;
            }

            if (in_array($postPlatform->status, ['published', 'cancelled'])) {
                return;
            }

            $this->runPublishWithOptionalAccountLock(
                $postPlatform,
                $registry,
                $tokenRefreshService,
                $postPublishingService,
            );
        } finally {
            $platformLock->release();
        }
    }

    private function skipIfAlreadyPublished(PostPublishingService $postPublishingService): bool
    {
        return (bool) DB::transaction(function () use ($postPublishingService) {
            $pp = PostPlatform::lockForUpdate()->find($this->postPlatformId);
            if ($pp === null) {
                return true;
            }
            if (in_array($pp->status, ['published', 'cancelled'], true)) {
                $post = $pp->post;
                if ($post !== null) {
                    $postPublishingService->finalizePostStatus($post);
                }
                return true;
            }
            $remoteId = trim((string) ($pp->platform_post_id ?? ''));
            if ($remoteId !== '') {
                if ($pp->status !== 'published') {
                    $pp->update([
                        'status'        => 'published',
                        'published_at'  => $pp->published_at ?? now(),
                        'error_message' => null,
                    ]);
                }
                $post = $pp->post;
                if ($post !== null) {
                    $postPublishingService->finalizePostStatus($post);
                }
                return true;
            }

            return false;
        });
    }

    private function runPublishWithOptionalAccountLock(
        PostPlatform $postPlatform,
        PlatformRegistry $registry,
        TokenRefreshService $tokenRefreshService,
        PostPublishingService $postPublishingService,
    ): void {
        $post = $postPlatform->post;

        if ($post === null) {
            $this->markFailed($postPlatform, 'Parent post has been deleted.');
            return;
        }

        if (in_array($post->status, ['draft', 'failed'])) {
            $this->markFailed($postPlatform, "Post is no longer eligible for publishing (status: {$post->status}).");
            $postPublishingService->finalizePostStatus($post);
            return;
        }

        $account = $postPlatform->socialAccount;

        if ($account === null) {
            $this->markFailed($postPlatform, 'Social account has been removed.');
            $postPublishingService->finalizePostStatus($post);
            return;
        }

        if ($account->status !== 'active') {
            $this->markFailed($postPlatform, "Social account is {$account->status}. Please reconnect.");
            $postPublishingService->finalizePostStatus($post);
            return;
        }

        if (empty($account->access_token)) {
            $this->markFailed($postPlatform, 'Social account has no access token. Please reconnect.');
            $postPublishingService->finalizePostStatus($post);
            return;
        }

        $platform = Platform::tryFrom($postPlatform->platform);

        if ($platform === null) {
            $this->markFailed($postPlatform, "Unknown platform: {$postPlatform->platform}");
            $postPublishingService->finalizePostStatus($post);
            return;
        }

        $initialRefreshOk = $tokenRefreshService->refreshIfNeeded($account);
        $account->refresh();

        if (!$initialRefreshOk) {
            Log::warning('Token refreshIfNeeded failed before publish; attempting forced refresh', [
                'post_platform_id' => $postPlatform->id,
                'account_id'       => $account->id,
                'platform'         => $account->platform,
            ]);
            $forcedRefreshOk = $tokenRefreshService->refresh($account);
            $account->refresh();

            if (!$forcedRefreshOk) {
                if (trim((string) $account->access_token) === '') {
                    $this->markFailed($postPlatform, 'Social account has no access token. Please reconnect.');
                    $postPublishingService->finalizePostStatus($post);
                    return;
                }
                if ($account->isTokenExpired()) {
                    $this->markFailed($postPlatform, 'Access token is expired and could not be refreshed. Please reconnect.');
                    $postPublishingService->finalizePostStatus($post);
                    return;
                }
                $this->markFailed(
                    $postPlatform,
                    'Could not refresh access token before publishing. Please reconnect your account.'
                );
                $postPublishingService->finalizePostStatus($post);
                return;
            }
        }

        if (trim((string) $account->access_token) === '') {
            $this->markFailed($postPlatform, 'Social account has no access token. Please reconnect.');
            $postPublishingService->finalizePostStatus($post);
            return;
        }

        if ($account->isTokenExpired()) {
            $this->markFailed($postPlatform, 'Access token is expired and could not be refreshed. Please reconnect.');
            $postPublishingService->finalizePostStatus($post);
            return;
        }

        if (Config::boolean('app.publish_per_account_lock', true)) {
            $accountLock = Cache::lock('publish_social_account:' . $account->id, 180);
            if (! $accountLock->get()) {
                $this->release(5);
                return;
            }
            try {
                $this->runAccountLockedPublishSection(
                    $postPlatform,
                    $post,
                    $platform,
                    $registry,
                    $tokenRefreshService,
                    $postPublishingService,
                    $account,
                );
            } finally {
                $accountLock->release();
            }
        } else {
            $this->runAccountLockedPublishSection(
                $postPlatform,
                $post,
                $platform,
                $registry,
                $tokenRefreshService,
                $postPublishingService,
                $account,
            );
        }
    }

    private function runAccountLockedPublishSection(
        PostPlatform $postPlatform,
        Post $post,
        Platform $platform,
        PlatformRegistry $registry,
        TokenRefreshService $tokenRefreshService,
        PostPublishingService $postPublishingService,
        SocialAccount $account,
    ): void {
        if ($this->isPlatformPaused($platform)) {
            $postPlatform->update([
                'status' => 'pending',
                'error_message' => "Publishing paused by admin for {$platform->label()}.",
            ]);
            $this->release(120);
            return;
        }

        try {
            $adapter = $registry->resolve($platform);
        } catch (\InvalidArgumentException $e) {
            $this->markFailed($postPlatform, $e->getMessage());
            $postPublishingService->finalizePostStatus($post);
            return;
        }

        if (trim($post->content) === '' && $postPlatform->platform_content === null) {
            $this->markFailed($postPlatform, 'Post content is empty.');
            $postPublishingService->finalizePostStatus($post);
            return;
        }

        $mediaUrls = $this->resolveMediaUrls($post);
        Log::info('Resolved post media before publish', [
            'post_platform_id' => $postPlatform->id,
            'post_id'          => $post->id,
            'platform'         => $platform->value,
            'media_count'      => count($mediaUrls),
            'media_urls'       => array_slice($mediaUrls, 0, 3),
            'publish_phase'    => 'media_resolved',
        ]);

        if ($platform === Platform::LinkedIn) {
            $postMediaCount = (int) $post->mediaFiles()->count();
            $fallbackMediaCount = is_array($post->media_paths) ? count($post->media_paths) : 0;
            if (($postMediaCount > 0 || $fallbackMediaCount > 0) && count($mediaUrls) === 0) {
                $this->markFailed(
                    $postPlatform,
                    'LinkedIn media was attached but could not be resolved for upload. Posting was blocked to avoid text-only publish.'
                );
                $postPublishingService->finalizePostStatus($post);
                return;
            }
        }

        $preflightError = app(MediaPublishPreflightService::class)->validatePostMediaForPlatform($post, $platform);
        if ($preflightError !== null) {
            $this->markFailed($postPlatform, $preflightError);
            $postPublishingService->finalizePostStatus($post);
            return;
        }

        try {
            $result = $adapter->publish(
                account:         $account,
                content:         $post->content,
                mediaUrls:       $mediaUrls,
                platformContent: $postPlatform->platform_content,
            );

            if (
                !$result->success
                && !empty($mediaUrls)
                && $this->isTextFallbackEnabled()
                && !$this->postHasAttachedMedia($post)
                && $this->canFallbackToTextOnly($platform, $result->errorMessage)
            ) {
                Log::warning('Retrying publish without media fallback', [
                    'post_platform_id' => $postPlatform->id,
                    'platform' => $platform->value,
                    'reason' => $result->errorMessage,
                ]);

                $result = $adapter->publish(
                    account:         $account,
                    content:         $post->content,
                    mediaUrls:       [],
                    platformContent: $postPlatform->platform_content,
                );
            }
        } catch (\Throwable $e) {
            Log::error('Platform adapter threw exception during publish', [
                'post_platform_id'  => $postPlatform->id,
                'platform'          => $platform->value,
                'error'             => $e->getMessage(),
                'error_class'       => PublishErrorClassifier::classify(null, $e->getMessage()),
            ]);

            $this->markFailed($postPlatform, 'Platform error: ' . $e->getMessage());
            $postPublishingService->finalizePostStatus($post);
            return;
        }

        if ($result->success) {
            $this->markPublished($postPlatform, $post->id, $platform, $result);
        } else {
            if (PublishErrorClassifier::matchesAuthFailure($result->errorCode, $result->errorMessage)) {
                Log::warning('Publish failed due to auth; forcing refresh and retrying once', [
                    'post_platform_id' => $postPlatform->id,
                    'platform'         => $platform->value,
                    'error'            => $result->errorMessage,
                ]);

                if ($tokenRefreshService->refresh($account)) {
                    $account->refresh();

                    try {
                        $retryResult = $adapter->publish(
                            account:         $account,
                            content:         $post->content,
                            mediaUrls:       $mediaUrls,
                            platformContent: $postPlatform->platform_content,
                        );

                        if ($retryResult->success) {
                            $result = $retryResult;
                        } else {
                            $postPlatform->update(['error_message' => $retryResult->errorMessage]);
                        }
                    } catch (\Throwable $e) {
                        Log::error('Retry publish after forced refresh failed with exception', [
                            'post_platform_id' => $postPlatform->id,
                            'platform'         => $platform->value,
                            'error'            => $e->getMessage(),
                        ]);
                    }
                }
            }

            if ($result->success) {
                $this->markPublished($postPlatform, $post->id, $platform, $result);
            } elseif (PublishErrorClassifier::matchesPermanentProviderFailure($result->errorCode, $result->errorMessage)) {
                $this->markFailed($postPlatform, $result->errorMessage ?? 'Provider rejected this request permanently.');
            } elseif ($this->attempts() >= $this->tries()) {
                $this->markFailed($postPlatform, $result->errorMessage ?? 'Unknown error');
            } else {
                $postPlatform->update(['error_message' => $result->errorMessage]);
                $backoff = $this->backoff();
                $this->release($backoff[$this->attempts() - 1] ?? 90);
                return;
            }
        }

        $postPublishingService->finalizePostStatus($post);
    }

    private function markFailed(PostPlatform $postPlatform, string $errorMessage): void
    {
        $postPlatform->update([
            'status'        => 'failed',
            'error_message' => $errorMessage,
        ]);

        Log::error('Post publish to platform failed permanently', [
            'post_platform_id' => $postPlatform->id,
            'platform'         => $postPlatform->platform,
            'error'            => $errorMessage,
            'error_class'      => PublishErrorClassifier::classify(null, $errorMessage),
        ]);

        try {
            $post = $postPlatform->post;
            $postRef = $post !== null ? "post #{$post->id} (user {$post->user_id})" : "post id {$postPlatform->post_id}";
            app(InAppNotificationService::class)->notifySuperAdminsOperationalAlert(
                'admin_critical_post_publish',
                'Post failed to publish',
                ucfirst((string) $postPlatform->platform) . ": {$postRef}. " . mb_substr($errorMessage, 0, 400),
                route('admin.operations'),
                ['post_platform_id' => $postPlatform->id],
                'post_publish_fail:' . $postPlatform->id,
                600,
            );
        } catch (\Throwable) {
        }

        $this->bumpUserCacheVersion($postPlatform);
    }

    private function markPublished(PostPlatform $postPlatform, int $postId, Platform $platform, \App\Services\Platform\PublishResult $result): void
    {
        $postPlatform->update([
            'status'           => 'published',
            'platform_post_id' => $result->platformPostId,
            'published_at'     => now(),
            'error_message'    => null,
        ]);

        $comment = trim((string) ($postPlatform->first_comment ?? ''));
        if ($comment !== '' && $result->platformPostId !== null) {
            $delayMinutes = $postPlatform->comment_delay_minutes;
            $delayMinutes = is_int($delayMinutes) ? $delayMinutes : (int) $delayMinutes;
            $delayMinutes = max(0, $delayMinutes);

            $postPlatform->update([
                'comment_status'        => 'queued',
                'comment_error_message' => null,
            ]);

            $commentSync = PublishExecutionMode::useSyncCommentJobs();
            if ($commentSync && $delayMinutes === 0) {
                PublishPostCommentJob::dispatchSync($postPlatform->id);
            } else {
                if ($commentSync && $delayMinutes > 0) {
                    Log::warning('First comment has delay > 0; queued job required despite sync comment preference', [
                        'post_platform_id' => $postPlatform->id,
                        'delay_minutes'    => $delayMinutes,
                    ]);
                }
                PublishPostCommentJob::dispatch($postPlatform->id)->delay(now()->addMinutes($delayMinutes));
            }
        }

        Log::info('Post published to platform', [
            'post_id'            => $postId,
            'platform'           => $platform->value,
            'platform_post_id'   => $result->platformPostId,
            'publish_phase'      => 'complete',
        ]);
        $this->bumpUserCacheVersion($postPlatform);
    }

    private function resolveMediaUrls($post): array
    {
        $mediaFiles = $post->mediaFiles;

        if ($mediaFiles !== null && !$mediaFiles->isEmpty()) {
            $urls = $mediaFiles->pluck('url')->filter()->values()->toArray();
            if ($urls !== []) {
                return $urls;
            }
        }

        $fallback = [];
        $mediaPaths = $post->media_paths;
        if (is_array($mediaPaths)) {
            foreach ($mediaPaths as $path) {
                if (!is_string($path) || trim($path) === '') {
                    continue;
                }
                $fallback[] = $this->toAbsoluteMediaUrl($path);
            }
        }

        return array_values(array_unique(array_filter($fallback)));
    }

    private function toAbsoluteMediaUrl(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return '';
        }

        if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
            return $trimmed;
        }

        return rtrim((string) config('app.url'), '/') . '/' . ltrim($trimmed, '/');
    }

    public function failed(\Throwable $exception): void
    {
        $postPlatform = PostPlatform::find($this->postPlatformId);

        if ($postPlatform !== null) {
            $this->markFailed($postPlatform, 'Job exception: ' . $exception->getMessage());

            $post = $postPlatform->post;
            if ($post !== null) {
                app(PostPublishingService::class)->finalizePostStatus($post);
            }
        }
    }

    private function postHasAttachedMedia(Post $post): bool
    {
        if ($post->relationLoaded('mediaFiles')) {
            if ($post->mediaFiles->isNotEmpty()) {
                return true;
            }
        } elseif ($post->mediaFiles()->exists()) {
            return true;
        }

        $paths = $post->media_paths;
        return is_array($paths) && count(array_filter($paths, fn ($p) => is_string($p) && trim($p) !== '')) > 0;
    }

    private function canFallbackToTextOnly(Platform $platform, ?string $errorMessage): bool
    {
        // Platforms where text-only fallback is not valid for our publish flow.
        if (in_array($platform, [Platform::Instagram, Platform::TikTok, Platform::LinkedIn], true)) {
            return false;
        }

        $msg = strtolower((string) $errorMessage);
        if ($msg === '') {
            return false;
        }

        $mediaSignals = [
            'media',
            'image',
            'video',
            'upload',
            'could not fetch',
            'invalid media',
        ];

        foreach ($mediaSignals as $signal) {
            if (str_contains($msg, $signal)) {
                return true;
            }
        }

        return false;
    }

    public function tries(): int
    {
        $policy = SiteSetting::getJson('publish_retry_policy', []);
        $tries = (int) ($policy['max_tries'] ?? 3);
        return max(1, min(10, $tries));
    }

    public function backoff(): array
    {
        $policy = SiteSetting::getJson('publish_retry_policy', []);
        $raw = $policy['backoff_seconds'] ?? [10, 30, 90];
        if (!is_array($raw) || $raw === []) {
            return [10, 30, 90];
        }

        $backoff = array_values(array_filter(array_map(
            fn ($v) => max(1, (int) $v),
            $raw
        )));

        return $backoff === [] ? [10, 30, 90] : $backoff;
    }

    private function isPlatformPaused(Platform $platform): bool
    {
        $paused = SiteSetting::getJson('paused_platforms', []);
        return in_array($platform->value, is_array($paused) ? $paused : [], true);
    }

    private function isTextFallbackEnabled(): bool
    {
        $policy = SiteSetting::getJson('publish_retry_policy', []);
        return (bool) ($policy['text_only_fallback'] ?? true);
    }

    private function bumpUserCacheVersion(PostPlatform $postPlatform): void
    {
        $userId = $postPlatform->post?->user_id
            ?? Post::whereKey($postPlatform->post_id)->value('user_id');

        if ($userId !== null) {
            app(UserCacheVersionService::class)->bump((int) $userId);
        }
    }
}
