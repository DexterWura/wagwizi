<?php

namespace App\Services\Post;

use App\Jobs\PublishPostToPlatformJob;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Services\Cache\UserCacheVersionService;
use App\Services\Notifications\InAppNotificationService;
use Illuminate\Support\Facades\Log;

class PostPublishingService
{
    /**
     * Dispatch publish jobs for all pending PostPlatform rows of a post.
     *
     * @param  bool  $syncWhenDue  When true (used by publishDuePosts), jobs may run synchronously per config.
     */
    public function dispatchPublishing(Post $post, bool $syncWhenDue = false): int
    {
        if ($post->status === 'published') {
            Log::warning('Attempted to publish already-published post', ['post_id' => $post->id]);
            return 0;
        }

        if (trim($post->content) === '') {
            Log::warning('Cannot publish post with empty content', ['post_id' => $post->id]);
            return 0;
        }

        $pendingPlatforms = $post->postPlatforms()
            ->where('status', 'pending')
            ->get();

        if ($pendingPlatforms->isEmpty()) {
            Log::warning('No pending platform targets for post', ['post_id' => $post->id]);
            return 0;
        }

        $post->update(['status' => 'publishing']);

        $runSync = PublishExecutionMode::useSyncPublish($syncWhenDue);

        foreach ($pendingPlatforms as $postPlatform) {
            $postPlatform->update(['status' => 'publishing']);
            if ($runSync) {
                try {
                    PublishPostToPlatformJob::dispatchSync($postPlatform->id);
                } catch (\Throwable $syncException) {
                    $postPlatform->update([
                        'status'        => 'failed',
                        'error_message' => 'Publish job failed: ' . $syncException->getMessage(),
                    ]);

                    Log::error('Synchronous publish failed', [
                        'post_id'          => $post->id,
                        'post_platform_id' => $postPlatform->id,
                        'error'            => $syncException->getMessage(),
                    ]);

                    try {
                        app(InAppNotificationService::class)->notifySuperAdminsOperationalAlert(
                            'admin_critical_post_queue',
                            'Scheduled publish could not run',
                            "Post #{$post->id}, platform {$postPlatform->platform}: " . mb_substr($syncException->getMessage(), 0, 400),
                            route('admin.operations'),
                            ['post_id' => $post->id, 'post_platform_id' => $postPlatform->id],
                            'post_publish_sync_fail:' . $postPlatform->id,
                            1800,
                        );
                    } catch (\Throwable) {
                    }
                }
                continue;
            }

            try {
                PublishPostToPlatformJob::dispatch($postPlatform->id);
            } catch (\Throwable $e) {
                Log::warning('Queue dispatch failed; falling back to sync publish execution', [
                    'post_id'          => $post->id,
                    'post_platform_id' => $postPlatform->id,
                    'error'            => $e->getMessage(),
                ]);

                try {
                    PublishPostToPlatformJob::dispatchSync($postPlatform->id);
                } catch (\Throwable $syncException) {
                    $postPlatform->update([
                        'status' => 'failed',
                        'error_message' => 'Unable to queue publish job: ' . $syncException->getMessage(),
                    ]);

                    Log::error('Sync publish fallback failed', [
                        'post_id'          => $post->id,
                        'post_platform_id' => $postPlatform->id,
                        'error'            => $syncException->getMessage(),
                    ]);

                    try {
                        app(InAppNotificationService::class)->notifySuperAdminsOperationalAlert(
                            'admin_critical_post_queue',
                            'Publish job could not run',
                            "Post #{$post->id}, platform {$postPlatform->platform}: " . mb_substr($syncException->getMessage(), 0, 400),
                            route('admin.operations'),
                            ['post_id' => $post->id, 'post_platform_id' => $postPlatform->id],
                            'post_queue_sync_fail:' . $postPlatform->id,
                            1800,
                        );
                    } catch (\Throwable) {
                    }
                }
            }
        }

        $count = $pendingPlatforms->count();

        Log::info('Publishing dispatched', [
            'post_id'         => $post->id,
            'user_id'         => $post->user_id,
            'jobs_dispatched' => $count,
            'platforms'       => $pendingPlatforms->pluck('platform')->toArray(),
        ]);
        app(UserCacheVersionService::class)->bump((int) $post->user_id);

        return $count;
    }

    /**
     * Find and publish all posts that are due for publishing.
     */
    public function publishDuePosts(): int
    {
        $posts = Post::dueForPublishing()->get();
        if ($posts->isNotEmpty()) {
            Log::info('Publish due posts batch', [
                'overdue_count' => $posts->count(),
                'post_ids'      => $posts->pluck('id')->take(50)->values()->all(),
            ]);
        }
        $dispatched = 0;

        foreach ($posts as $post) {
            $dispatched += $this->dispatchPublishing($post, true);
        }

        return $dispatched;
    }

    /**
     * Reset failed platform rows to pending and dispatch publish jobs again.
     */
    public function retryFailedPlatforms(Post $post): int
    {
        if (trim($post->content) === '') {
            Log::warning('Cannot retry publish with empty content', ['post_id' => $post->id]);
            return 0;
        }

        $failed = $post->postPlatforms()->where('status', 'failed')->get();
        if ($failed->isEmpty()) {
            Log::warning('No failed platform targets to retry', ['post_id' => $post->id]);
            return 0;
        }

        foreach ($failed as $pp) {
            $pp->update([
                'status'        => 'pending',
                'error_message' => null,
            ]);
        }

        $post->update(['status' => 'publishing']);

        return $this->dispatchPublishing($post, false);
    }

    /**
     * Check if all platform targets for a post are done (published or failed).
     * If so, update the post status accordingly.
     */
    public function finalizePostStatus(Post $post): void
    {
        $platforms = $post->postPlatforms;

        if ($platforms->isEmpty()) {
            return;
        }

        $allDone = $platforms->every(fn (PostPlatform $pp) => in_array($pp->status, ['published', 'failed']));

        if (!$allDone) {
            return;
        }

        $anyPublished = $platforms->contains(fn (PostPlatform $pp) => $pp->status === 'published');

        $finalStatus = $anyPublished ? 'published' : 'failed';

        $post->update([
            'status'       => $finalStatus,
            'published_at' => $anyPublished ? now() : null,
        ]);

        $published = $platforms->where('status', 'published')->count();
        $failed    = $platforms->where('status', 'failed')->count();

        Log::info('Post finalized', [
            'post_id'   => $post->id,
            'status'    => $finalStatus,
            'published' => $published,
            'failed'    => $failed,
        ]);
        app(UserCacheVersionService::class)->bump((int) $post->user_id);
    }
}
