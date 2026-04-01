<?php

namespace App\Services\Post;

use App\Jobs\PublishPostToPlatformJob;
use App\Models\Post;
use App\Models\PostPlatform;
use Illuminate\Support\Facades\Log;

class PostPublishingService
{
    /**
     * Dispatch publish jobs for all pending PostPlatform rows of a post.
     */
    public function dispatchPublishing(Post $post): int
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

        $post->update(['status' => 'queued']);

        foreach ($pendingPlatforms as $postPlatform) {
            $postPlatform->update(['status' => 'queued']);
            PublishPostToPlatformJob::dispatch($postPlatform->id);
        }

        $count = $pendingPlatforms->count();

        Log::info('Publishing dispatched', [
            'post_id'         => $post->id,
            'user_id'         => $post->user_id,
            'jobs_dispatched' => $count,
            'platforms'       => $pendingPlatforms->pluck('platform')->toArray(),
        ]);

        return $count;
    }

    /**
     * Find and publish all posts that are due for publishing.
     */
    public function publishDuePosts(): int
    {
        $posts = Post::dueForPublishing()->get();
        $dispatched = 0;

        foreach ($posts as $post) {
            $dispatched += $this->dispatchPublishing($post);
        }

        return $dispatched;
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
    }
}
