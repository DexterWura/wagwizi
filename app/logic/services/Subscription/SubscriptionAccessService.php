<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Models\Plan;
use App\Models\User;

/**
 * Guards against duplicate checkout / plan changes when the user already has access.
 */
final class SubscriptionAccessService
{
    /**
     * Same plan, subscription still valid (active paid period or trialing) — not renewal after lapse.
     */
    public function userHasActiveAccessToPlan(User $user, Plan $plan): bool
    {
        $sub = $user->subscription;
        if ($sub === null || $sub->plan_id !== $plan->id) {
            return false;
        }

        if ($sub->status === 'past_due') {
            return false;
        }

        return $sub->isActive() || $sub->isTrialing();
    }

    /**
     * User is on this plan but payment / period lapsed — checkout to renew is allowed.
     */
    public function userMustRenewSamePlan(User $user, Plan $plan): bool
    {
        $sub = $user->subscription;

        return $sub !== null
            && $sub->plan_id === $plan->id
            && $sub->status === 'past_due';
    }
}
