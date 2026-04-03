<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Models\Subscription;
use App\Models\User;

/**
 * Expires paid monthly (or finite) periods: active → past_due when current_period_end passes.
 */
final class SubscriptionPeriodService
{
    public function expirePaidActivePeriodIfDue(?User $user): void
    {
        if ($user === null) {
            return;
        }

        $sub = $user->subscription;
        if ($sub === null || $sub->status !== 'active') {
            return;
        }

        if ($sub->current_period_end === null) {
            return;
        }

        if ($sub->current_period_end->isFuture()) {
            return;
        }

        $sub->loadMissing('planModel');
        $plan = $sub->planModel;
        if ($plan === null || $plan->is_free || $plan->is_lifetime) {
            return;
        }

        Subscription::query()
            ->whereKey($sub->id)
            ->update(['status' => 'past_due']);
    }
}
