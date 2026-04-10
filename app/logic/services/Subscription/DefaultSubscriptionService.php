<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Ai\PlatformAiQuotaService;

final class DefaultSubscriptionService
{
    public function assignFreePlanToUser(User $user): void
    {
        $plan = Plan::query()
            ->where('is_active', true)
            ->where('is_free', true)
            ->orderBy('sort_order')
            ->first();

        if ($plan === null) {
            return;
        }

        $sub = Subscription::updateOrCreate(
            ['user_id' => $user->id],
            [
                'plan_id'              => $plan->id,
                'plan'                 => $plan->slug,
                'billing_interval'     => null,
                'status'               => 'active',
                'current_period_start' => now(),
                'current_period_end'   => null,
            ],
        );

        app(PlatformAiQuotaService::class)->applyPlanBudgetToSubscription($sub, $plan);
    }
}
