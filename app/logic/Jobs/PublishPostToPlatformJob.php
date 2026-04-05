<?php

namespace App\Jobs;

use App\Jobs\PublishPostCommentJob;
use App\Models\PostPlatform;
use App\Models\SiteSetting;
use App\Services\Platform\Platform;
use App\Services\Platform\PlatformRegistry;
use App\Services\Post\PostPublishingService;
use App\Services\SocialAccount\TokenRefreshService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

        $refreshOk = $tokenRefreshService->refreshIfNeeded($account);
        if (!$refreshOk) {
            Log::warning('Token refresh attempt failed before publish; continuing with current token', [
                'post_platform_id' => $postPlatform->id,
                'account_id'       => $account->id,
                'platform'         => $account->platform,
            ]);
        }
        $account->refresh();

        if ($account->isTokenExpired()) {
            $this->markFailed($postPlatform, 'Access token is expired and could not be refreshed. Please reconnect.');
            $postPublishingService->finalizePostStatus($post);
            return;
        }

        $platform = Platform::tryFrom($postPlatform->platform);

        if ($platform === null) {
            $this->markFailed($postPlatform, "Unknown platform: {$postPlatform->platform}");
            $postPublishingService->finalizePostStatus($post);
            return;
        }

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
                'post_platform_id' => $postPlatform->id,
                'platform'         => $platform->value,
                'error'            => $e->getMessage(),
            ]);

            $this->markFailed($postPlatform, 'Platform error: ' . $e->getMessage());
            $postPublishingService->finalizePostStatus($post);
            return;
        }

        if ($result->success) {
            $this->markPublished($postPlatform, $post->id, $platform, $result);
        } else {
            if ($this->isAuthFailure($result->errorCode, $result->errorMessage)) {
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
            } elseif ($this->isPermanentProviderFailure($result->errorCode, $result->errorMessage)) {
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
        ]);
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
                'comment_status' => 'queued',
                'comment_error_message' => null,
            ]);

            PublishPostCommentJob::dispatch($postPlatform->id)->delay(now()->addMinutes($delayMinutes));
        }

        Log::info('Post published to platform', [
            'post_id'      => $postId,
            'platform'     => $platform->value,
            'platform_post_id' => $result->platformPostId,
        ]);
    }

    private function resolveMediaUrls($post): array
    {
        $mediaFiles = $post->mediaFiles;

        if ($mediaFiles === null || $mediaFiles->isEmpty()) {
            return [];
        }

        return $mediaFiles->pluck('url')->filter()->values()->toArray();
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

    private function isAuthFailure(?int $errorCode, ?string $errorMessage): bool
    {
        if (in_array($errorCode, [401, 403], true)) {
            return true;
        }

        $msg = strtolower((string) $errorMessage);
        if ($msg === '') {
            return false;
        }

        $authSignals = [
            'invalid token',
            'token expired',
            'expired token',
            'unauthorized',
            'permission',
            'invalid oauth',
            'authentication',
            'forbidden',
            'access denied',
        ];

        foreach ($authSignals as $signal) {
            if (str_contains($msg, $signal)) {
                return true;
            }
        }

        return false;
    }

    private function isPermanentProviderFailure(?int $errorCode, ?string $errorMessage): bool
    {
        if ($errorCode === 402) {
            return true;
        }

        $msg = strtolower((string) $errorMessage);
        if ($msg === '') {
            return false;
        }

        $signals = [
            'creditsdepleted',
            'credit',
            'quota exceeded',
            'insufficient balance',
            'billing',
            'payment required',
            'upgrade your plan',
        ];

        foreach ($signals as $signal) {
            if (str_contains($msg, $signal)) {
                return true;
            }
        }

        return false;
    }
}
