<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Models\User;

/**
 * Gates inbound webhook access on the user's current subscription plan.
 */
final class PlanWebhookFeatureService
{
    public function userMayUseWebhooks(int $userId): bool
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $user->loadMissing('subscription.planModel');
        $subscription = $user->subscription;
        $plan = $subscription?->planModel;

        if ($subscription === null || $plan === null) {
            return false;
        }

        if (!($subscription->isActive() || $subscription->isTrialing())) {
            return false;
        }

        return (bool) ($plan->includes_webhooks ?? false);
    }
}

