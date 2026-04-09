<?php

namespace App\Services\Post;

use Illuminate\Support\Facades\Config;

/**
 * Central place for sync vs queued publish/comment execution (no-worker vs worker deployments).
 */
final class PublishExecutionMode
{
    /**
     * Use synchronous PublishPostToPlatformJob when true for this context.
     */
    public static function useSyncPublish(bool $duePostsContext): bool
    {
        if (Config::boolean('app.publish_all_jobs_sync', false)) {
            return true;
        }

        return $duePostsContext && Config::boolean('app.publish_due_posts_sync', true);
    }

    /**
     * Run first-comment job synchronously when possible (delay must be 0; otherwise still queued).
     */
    public static function useSyncCommentJobs(): bool
    {
        if (Config::boolean('app.publish_all_jobs_sync', false)) {
            return true;
        }

        return Config::boolean('app.publish_comment_jobs_sync', false);
    }
}
