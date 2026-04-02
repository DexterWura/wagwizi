<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

final class DefaultSubscriptionService
{
    public function assignFreePlanToUser(User $user): void
    {
        $plan = Plan::query()
            ->where('slug', 'free')
            ->where('is_active', true)
            ->first();

        if ($plan === null) {
            return;
        }

        Subscription::updateOrCreate(
            ['user_id' => $user->id],
            [
                'plan_id'              => $plan->id,
                'plan'                 => $plan->slug,
                'status'               => 'active',
                'current_period_start' => now(),
                'current_period_end'   => null,
            ],
        );
    }
}
