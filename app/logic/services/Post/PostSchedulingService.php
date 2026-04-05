<?php

namespace App\Services\Post;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\PostPlatform;
use App\Models\MediaFile;
use App\Services\Platform\PlatformRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PostSchedulingService
{
    private const MAX_SCHEDULE_DAYS_AHEAD = 90;
    private const MIN_SCHEDULE_MINUTES_AHEAD = 5;

    public function createDraft(int $userId, array $data): Post
    {
        $this->validateContent($data);

        $post = DB::transaction(function () use ($userId, $data): Post {
            $post = Post::create([
                'user_id'  => $userId,
                'content'  => trim($data['content']),
                'platforms' => [],
                'status'   => 'draft',
            ]);

            if (!empty($data['platform_accounts']) && is_array($data['platform_accounts'])) {
                $this->validatePlatformAccountsOwnership($userId, $data['platform_accounts']);
                $this->syncPlatformAccounts(
                    $post,
                    $userId,
                    $data['platform_accounts'],
                    $data['platform_content'] ?? [],
                    $data['first_comment'] ?? null,
                    $this->resolveCommentDelayMinutes($data),
                );
            }

            $this->syncPostMedia($post, $userId, $data['media_file_id'] ?? null);

            return $post;
        });

        Log::info('Post draft created', ['user_id' => $userId, 'post_id' => $post->id]);

        return $post;
    }

    public function updatePost(int $userId, int $postId, array $data): Post
    {
        $post = Post::where('user_id', $userId)->findOrFail($postId);

        if ($post->status === 'published') {
            throw new InvalidArgumentException('Cannot update a published post.');
        }

        if (in_array($post->status, ['publishing'])) {
            throw new InvalidArgumentException('Cannot update a post that is currently being published.');
        }

        if (isset($data['content'])) {
            $this->validateContent($data);
            $data['content'] = trim($data['content']);
        }

        if (isset($data['scheduled_at'])) {
            $this->validateScheduleTime($data['scheduled_at']);
        }

        $post->update(array_filter([
            'content'      => $data['content'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
        ], fn ($v) => $v !== null));

        if (isset($data['platform_accounts'])) {
            $platformAccounts = is_array($data['platform_accounts'])
                ? $data['platform_accounts']
                : [];

            if ($platformAccounts === []) {
                // Editing a failed/draft post should allow clearing all targets first.
                $post->postPlatforms()->delete();
                $post->update(['platforms' => []]);
            } else {
                $this->syncPlatformAccounts(
                    $post,
                    $userId,
                    $platformAccounts,
                    $data['platform_content'] ?? [],
                    $data['first_comment'] ?? null,
                    $this->resolveCommentDelayMinutes($data),
                );
            }
        }

        if (array_key_exists('media_file_id', $data)) {
            $this->syncPostMedia($post, $userId, $data['media_file_id']);
        }

        return $post->fresh();
    }

    public function schedulePost(int $userId, array $data): Post
    {
        $this->validateContent($data);
        $scheduledAt = $this->resolveScheduleFromInput($data);

        if (empty($data['platform_accounts']) || !is_array($data['platform_accounts'])) {
            throw new InvalidArgumentException('At least one platform account must be selected.');
        }

        $this->validatePlatformAccountsOwnership($userId, $data['platform_accounts']);

        return DB::transaction(function () use ($userId, $data, $scheduledAt): Post {
            $post = Post::create([
                'user_id'      => $userId,
                'content'      => trim($data['content']),
                'platforms'    => [],
                'status'       => 'scheduled',
                'scheduled_at' => $scheduledAt,
            ]);

            $this->syncPlatformAccounts(
                $post,
                $userId,
                $data['platform_accounts'],
                $data['platform_content'] ?? [],
                $data['first_comment'] ?? null,
                $this->resolveCommentDelayMinutes($data),
            );

            $this->syncPostMedia($post, $userId, $data['media_file_id'] ?? null);

            Log::info('Post scheduled', [
                'user_id'      => $userId,
                'post_id'      => $post->id,
                'scheduled_at' => $scheduledAt->toIso8601String(),
                'platforms'    => count($data['platform_accounts']),
            ]);

            return $post;
        });
    }

    public function publishNow(int $userId, array $data): Post
    {
        $this->validateContent($data);

        if (empty($data['platform_accounts']) || !is_array($data['platform_accounts'])) {
            throw new InvalidArgumentException('At least one platform account must be selected.');
        }

        $this->validatePlatformAccountsOwnership($userId, $data['platform_accounts']);

        return DB::transaction(function () use ($userId, $data): Post {
            $post = Post::create([
                'user_id' => $userId,
                'content' => trim($data['content']),
                'platforms' => [],
                'status'  => 'publishing',
            ]);

            $this->syncPlatformAccounts(
                $post,
                $userId,
                $data['platform_accounts'],
                $data['platform_content'] ?? [],
                $data['first_comment'] ?? null,
                $this->resolveCommentDelayMinutes($data),
            );

            $this->syncPostMedia($post, $userId, $data['media_file_id'] ?? null);

            Log::info('Post queued for immediate publish', [
                'user_id'   => $userId,
                'post_id'   => $post->id,
                'platforms' => count($data['platform_accounts']),
            ]);

            return $post;
        });
    }

    public function cancelScheduled(int $userId, int $postId): Post
    {
        $post = Post::where('user_id', $userId)->findOrFail($postId);

        if ($post->status !== 'scheduled') {
            throw new InvalidArgumentException('Only scheduled posts can be cancelled.');
        }

        $hasPublished = $post->postPlatforms()
            ->where('status', 'published')
            ->exists();

        if ($hasPublished) {
            throw new InvalidArgumentException('Cannot cancel — some platforms have already published this post.');
        }

        $post->update([
            'status'       => 'draft',
            'scheduled_at' => null,
        ]);

        $post->postPlatforms()
            ->whereIn('status', ['pending', 'publishing'])
            ->update(['status' => 'cancelled']);

        Log::info('Scheduled post cancelled', ['user_id' => $userId, 'post_id' => $postId]);

        return $post->fresh();
    }

    public function deletePost(int $userId, int $postId): void
    {
        $post = Post::where('user_id', $userId)->findOrFail($postId);

        if ($post->status === 'published') {
            throw new InvalidArgumentException('Cannot delete a published post. Use unpublish first.');
        }

        if ($post->status === 'publishing') {
            throw new InvalidArgumentException('Cannot delete a post that is currently being published.');
        }

        $post->postPlatforms()->delete();
        $post->delete();

        Log::info('Post deleted', ['user_id' => $userId, 'post_id' => $postId]);
    }

    public function scheduleExistingPost(int $userId, int $postId, array $data): Post
    {
        $post = Post::where('user_id', $userId)->findOrFail($postId);

        if (in_array($post->status, ['published', 'publishing'], true)) {
            throw new InvalidArgumentException("Cannot schedule a post with status '{$post->status}'.");
        }

        if (empty($data['platform_accounts']) || !is_array($data['platform_accounts'])) {
            throw new InvalidArgumentException('At least one platform account must be selected.');
        }

        $this->validatePlatformAccountsOwnership($userId, $data['platform_accounts']);

        $scheduledAt = $this->resolveScheduleFromInput($data);

        return DB::transaction(function () use ($post, $userId, $data, $scheduledAt): Post {
            $post->update([
                'status'       => 'scheduled',
                'scheduled_at' => $scheduledAt,
            ]);

            $this->syncPlatformAccounts(
                $post,
                $userId,
                $data['platform_accounts'],
                $data['platform_content'] ?? [],
                $data['first_comment'] ?? null,
                $this->resolveCommentDelayMinutes($data),
            );

            $this->syncPostMedia($post, $userId, $data['media_file_id'] ?? null);

            return $post->fresh()->load('postPlatforms');
        });
    }

    private function validateContent(array $data): void
    {
        if (!isset($data['content']) || trim($data['content']) === '') {
            throw new InvalidArgumentException('Post content cannot be empty.');
        }
    }

    private function validateScheduleTime(string $dateTimeString): \Carbon\Carbon
    {
        try {
            $scheduledAt = \Carbon\Carbon::parse($dateTimeString);
        } catch (\Exception) {
            throw new InvalidArgumentException('Invalid date format for scheduled time.');
        }

        if ($scheduledAt->isPast()) {
            throw new InvalidArgumentException('Scheduled time must be in the future.');
        }

        if ($scheduledAt->diffInMinutes(now()) < self::MIN_SCHEDULE_MINUTES_AHEAD) {
            throw new InvalidArgumentException(
                'Scheduled time must be at least ' . self::MIN_SCHEDULE_MINUTES_AHEAD . ' minutes from now.'
            );
        }

        if ($scheduledAt->diffInDays(now()) > self::MAX_SCHEDULE_DAYS_AHEAD) {
            throw new InvalidArgumentException(
                'Posts cannot be scheduled more than ' . self::MAX_SCHEDULE_DAYS_AHEAD . ' days in advance.'
            );
        }

        return $scheduledAt;
    }

    private function resolveScheduleFromInput(array $data): \Carbon\Carbon
    {
        $scheduledAt = $data['scheduled_at'] ?? null;
        if (is_string($scheduledAt) && trim($scheduledAt) !== '') {
            return $this->validateScheduleTime($scheduledAt);
        }

        $delayValue = isset($data['delay_value']) ? (int) $data['delay_value'] : null;
        $delayUnit = $data['delay_unit'] ?? null;

        if ($delayValue === null || $delayValue <= 0 || !in_array($delayUnit, ['minutes', 'hours'], true)) {
            throw new InvalidArgumentException('Provide schedule date/time or a valid delay in minutes/hours.');
        }

        $scheduled = now()->addMinutes($delayUnit === 'hours' ? $delayValue * 60 : $delayValue);
        return $this->validateScheduleTime($scheduled->toDateTimeString());
    }

    private function resolveCommentDelayMinutes(array $data): ?int
    {
        if (!isset($data['comment_delay_value'], $data['comment_delay_unit'])) {
            return null;
        }

        $value = (int) $data['comment_delay_value'];
        $unit = $data['comment_delay_unit'];

        if ($value <= 0 || !in_array($unit, ['minutes', 'hours'], true)) {
            return null;
        }

        return $unit === 'hours' ? $value * 60 : $value;
    }

    private function validatePlatformAccountsOwnership(int $userId, array $accountIds): void
    {
        $ownedCount = SocialAccount::where('user_id', $userId)
            ->active()
            ->whereIn('id', $accountIds)
            ->count();

        if ($ownedCount !== count(array_unique($accountIds))) {
            throw new InvalidArgumentException(
                'One or more selected accounts do not belong to you or are not active.'
            );
        }
    }

    /**
     * @param array $platformAccounts Array of social_account_id values
     * @param array $platformContent  Keyed by social_account_id, per-platform content overrides
     */
    private function syncPlatformAccounts(
        Post $post,
        int $userId,
        array $platformAccounts,
        array $platformContent = [],
        ?string $firstComment = null,
        ?int $commentDelayMinutes = null,
    ): void
    {
        $post->postPlatforms()->delete();

        $accounts = SocialAccount::where('user_id', $userId)
            ->active()
            ->whereIn('id', $platformAccounts)
            ->get();

        if ($accounts->isEmpty()) {
            throw new InvalidArgumentException('None of the selected accounts are active.');
        }

        $platformSlugs = array_values($accounts->pluck('platform')->unique()->values()->toArray());
        $post->update(['platforms' => $platformSlugs]);

        foreach ($accounts as $account) {
            app(PlatformRegistry::class)->resolveBySlug($account->platform);

            if (empty($account->access_token)) {
                throw new InvalidArgumentException(
                    "Selected {$account->platform} account is missing access token. Please reconnect it."
                );
            }

            if (empty($account->platform_user_id)) {
                throw new InvalidArgumentException(
                    "Selected {$account->platform} account is missing platform user id. Please reconnect it."
                );
            }

            $overrideContent = $platformContent[$account->id] ?? null;

            if ($overrideContent !== null && trim($overrideContent) === '') {
                $overrideContent = null;
            }

            PostPlatform::create([
                'post_id'           => $post->id,
                'social_account_id' => $account->id,
                'platform'          => $account->platform,
                'platform_content'  => $overrideContent,
                'first_comment'     => $firstComment !== null && trim($firstComment) !== '' ? trim($firstComment) : null,
                'comment_delay_minutes' => $commentDelayMinutes,
                'comment_status'    => $firstComment !== null && trim($firstComment) !== '' ? 'pending' : null,
                'status'            => 'pending',
            ]);
        }
    }

    private function syncPostMedia(Post $post, int $userId, mixed $mediaFileId): void
    {
        if ($mediaFileId === null || $mediaFileId === '') {
            $post->mediaFiles()->sync([]);
            return;
        }

        $id = (int) $mediaFileId;
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid media selection.');
        }

        $media = MediaFile::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if ($media === null) {
            throw new InvalidArgumentException('Selected media is not available in your library.');
        }

        $post->mediaFiles()->sync([
            $media->id => ['sort_order' => 0],
        ]);
    }
}
