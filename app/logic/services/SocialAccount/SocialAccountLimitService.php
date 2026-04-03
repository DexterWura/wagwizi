<?php

declare(strict_types=1);

namespace App\Services\SocialAccount;

use App\Models\Plan;
use App\Models\SocialAccount;
use App\Models\User;
use InvalidArgumentException;

/**
 * Enforces {@see Plan::max_social_profiles} for the user's effective plan (subscription plan, else default free plan).
 */
final class SocialAccountLimitService
{
    public function effectivePlan(User $user): ?Plan
    {
        $user->loadMissing('subscription.planModel');
        $plan = $user->subscription?->planModel;
        if ($plan !== null) {
            return $plan;
        }

        return Plan::query()
            ->where('is_active', true)
            ->where('is_free', true)
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * @return int|null Cap on active connections; null means unlimited.
     */
    public function maxActiveAccountsAllowed(User $user): ?int
    {
        if ($user->isSuperAdmin()) {
            return null;
        }

        $plan = $this->effectivePlan($user);
        if ($plan === null) {
            return null;
        }

        return $plan->max_social_profiles;
    }

    public function activeAccountCount(User $user): int
    {
        return SocialAccount::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->count();
    }

    public function canAddAnotherAccount(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $max = $this->maxActiveAccountsAllowed($user);
        if ($max === null) {
            return true;
        }

        return $this->activeAccountCount($user) < $max;
    }

    public function rejectionMessageForNewConnection(User $user): string
    {
        $max = $this->maxActiveAccountsAllowed($user);
        if ($max === null) {
            return 'Cannot add another account.';
        }

        return "Your plan allows up to {$max} connected accounts. Disconnect one or upgrade your plan to add more.";
    }

    public function assertCanAddAccount(User $user): void
    {
        if ($this->canAddAnotherAccount($user)) {
            return;
        }

        throw new InvalidArgumentException($this->rejectionMessageForNewConnection($user));
    }

    /**
     * @return array{canAdd: bool, max: int|null, active: int}
     */
    public function summary(User $user): array
    {
        $max    = $this->maxActiveAccountsAllowed($user);
        $active = $this->activeAccountCount($user);
        $canAdd = $max === null || $active < $max || $user->isSuperAdmin();

        return [
            'canAdd' => $canAdd,
            'max'    => $max,
            'active' => $active,
        ];
    }
}
