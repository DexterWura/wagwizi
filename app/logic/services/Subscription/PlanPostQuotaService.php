<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Models\Plan;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Enforces plan-based post creation quotas per active billing period.
 */
final class PlanPostQuotaService
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

    public function allowedPostsForCurrentPeriod(User $user): ?int
    {
        if ($user->isSuperAdmin()) {
            return null;
        }

        $plan = $this->effectivePlan($user);
        if ($plan === null || $plan->max_scheduled_posts_per_month === null) {
            return null;
        }

        $monthlyCap = (int) $plan->max_scheduled_posts_per_month;
        if ($monthlyCap <= 0) {
            return null;
        }
        $multiplier = $this->billingIntervalMultiplier($user);

        return $monthlyCap * $multiplier;
    }

    public function usedPostsForCurrentPeriod(User $user): int
    {
        [$start, $end] = $this->currentPeriodRange($user);

        return Post::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['scheduled', 'publishing', 'published', 'failed'])
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end)
            ->count();
    }

    public function assertCanConsumeSlots(User $user, int $additionalSlots = 1): void
    {
        if ($additionalSlots <= 0) {
            return;
        }

        $allowed = $this->allowedPostsForCurrentPeriod($user);
        if ($allowed === null) {
            return;
        }

        $used = $this->usedPostsForCurrentPeriod($user);
        if (($used + $additionalSlots) <= $allowed) {
            return;
        }

        throw new InvalidArgumentException(
            "Post limit reached for your billing period ({$used}/{$allowed} used). Upgrade your plan or wait for your period reset."
        );
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function currentPeriodRange(User $user): array
    {
        $user->loadMissing('subscription');
        $sub = $user->subscription;
        $now = now();

        if ($sub === null) {
            return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
        }

        if ($sub->current_period_start !== null && $sub->current_period_end !== null) {
            return [$sub->current_period_start->copy(), $sub->current_period_end->copy()];
        }

        if ($sub->current_period_start !== null) {
            $start = $sub->current_period_start->copy();
            $end = (($sub->billing_interval ?? 'monthly') === 'yearly')
                ? $start->copy()->addYear()
                : $start->copy()->addMonth();

            return [$start, $end];
        }

        return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
    }

    private function billingIntervalMultiplier(User $user): int
    {
        $user->loadMissing('subscription');
        $interval = strtolower(trim((string) ($user->subscription?->billing_interval ?? 'monthly')));

        return $interval === 'yearly' ? 12 : 1;
    }
}

