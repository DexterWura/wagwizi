<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Models\User;
use InvalidArgumentException;

/**
 * Gates “first comment” / reply-style publishing on the user’s current plan.
 */
final class PlanReplyFeatureService
{
    public function userMayUseFirstCommentReplies(int $userId): bool
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $user->loadMissing('subscription.planModel');
        $plan = $user->subscription?->planModel;

        if ($plan === null) {
            return false;
        }

        return (bool) $plan->includes_replies;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function dataRequestsFirstComment(array $data): bool
    {
        $fc = trim((string) ($data['first_comment'] ?? ''));
        if ($fc !== '') {
            return true;
        }

        $delay = $data['comment_delay_value'] ?? null;

        return $delay !== null && $delay !== '' && (int) $delay > 0;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function assertUserMayUseReplies(int $userId, array $data): void
    {
        if (! $this->dataRequestsFirstComment($data)) {
            return;
        }

        if ($this->userMayUseFirstCommentReplies($userId)) {
            return;
        }

        throw new InvalidArgumentException(
            'Your plan does not include first-comment (reply) publishing. Remove the first comment and delay, or upgrade to a plan that includes replies.'
        );
    }
}
