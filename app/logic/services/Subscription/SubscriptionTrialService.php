<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Models\Plan;
use App\Models\PlanChange;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SubscriptionTrialService
{
    public function canStartTrial(User $user, Plan $plan): bool
    {
        if ($plan->is_free || $plan->is_lifetime) {
            return false;
        }

        if (! $plan->has_free_trial || $plan->free_trial_days === null || $plan->free_trial_days < 1) {
            return false;
        }

        $sub = $user->subscription;
        if ($sub === null) {
            return true;
        }

        if ($sub->status === 'trialing' && $sub->trial_ends_at !== null && $sub->trial_ends_at->isFuture()) {
            return false;
        }

        if ($sub->status === 'past_due') {
            return false;
        }

        if ($sub->status === 'active') {
            $sub->loadMissing('planModel');
            $current = $sub->planModel;
            if ($current !== null && ! $current->is_free) {
                return false;
            }
        }

        return true;
    }

    /**
     * Starts a time-limited trial without checkout (paid plan features until trial_ends_at).
     */
    public function startTrial(User $user, Plan $newPlan, ?int $oldPlanId): Subscription
    {
        return DB::transaction(function () use ($user, $newPlan, $oldPlanId): Subscription {
            $days = max(1, (int) $newPlan->free_trial_days);
            $ends = now()->addDays($days)->endOfDay();

            if ($newPlan->is_lifetime && $newPlan->hasReachedLifetimeCap()) {
                throw new \RuntimeException('Lifetime plan is full.');
            }

            $subscription = Subscription::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'plan_id'                 => $newPlan->id,
                    'plan'                    => $newPlan->slug,
                    'gateway'                 => null,
                    'gateway_subscription_id' => null,
                    'status'                  => 'trialing',
                    'current_period_start'    => now(),
                    'current_period_end'      => $ends,
                    'trial_ends_at'           => $ends,
                ]
            );

            if ($oldPlanId !== $newPlan->id) {
                if ($newPlan->is_lifetime) {
                    $newPlan->increment('lifetime_current_count');
                }

                if ($oldPlanId) {
                    $oldPlan = Plan::find($oldPlanId);
                    if ($oldPlan?->is_lifetime) {
                        $oldPlan->decrement('lifetime_current_count');
                    }
                }

                PlanChange::create([
                    'user_id'      => $user->id,
                    'from_plan_id' => $oldPlanId,
                    'to_plan_id'   => $newPlan->id,
                    'change_type'  => 'upgrade',
                ]);
            }

            return $subscription;
        });
    }

    /**
     * Marks trialing subscriptions as past_due when the trial window has ended.
     */
    public function expireTrialingIfDue(?User $user): void
    {
        if ($user === null) {
            return;
        }

        Subscription::query()
            ->where('user_id', $user->id)
            ->where('status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->update([
                'status'             => 'past_due',
                'current_period_end' => now(),
            ]);
    }
}
