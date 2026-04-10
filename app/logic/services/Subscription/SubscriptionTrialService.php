<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Models\Plan;
use App\Models\PlanChange;
use App\Models\Subscription;
use App\Models\User;
use App\Jobs\QueueTemplatedEmailForUserJob;
use App\Services\Ai\PlatformAiQuotaService;
use App\Services\Notifications\InAppNotificationService;
use Illuminate\Support\Facades\DB;

final class SubscriptionTrialService
{
    public function __construct(
        private readonly DefaultSubscriptionService $defaultSubscriptionService,
    ) {}

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
                    'billing_interval'        => null,
                    'gateway'                 => null,
                    'gateway_subscription_id' => null,
                    'status'                  => 'trialing',
                    'current_period_start'    => now(),
                    'current_period_end'      => $ends,
                    'trial_ends_at'           => $ends,
                ]
            );

            app(PlatformAiQuotaService::class)->applyPlanBudgetToSubscription($subscription, $newPlan);

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

            DB::afterCommit(function () use ($user, $newPlan): void {
                QueueTemplatedEmailForUserJob::dispatch($user->id, 'subscription.updated', [
                    'planName'     => $newPlan->name,
                    'trialStarted' => true,
                ]);
            });

            DB::afterCommit(function () use ($user, $newPlan): void {
                try {
                    app(InAppNotificationService::class)->notifySuperAdminsTrialStarted($user, $newPlan);
                } catch (\Throwable) {
                }
            });

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

        $expired = Subscription::query()
            ->where('user_id', $user->id)
            ->where('status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->get();

        if ($expired->isEmpty()) {
            return;
        }

        foreach ($expired as $sub) {
            $fromPlanId = $sub->plan_id;
            $this->defaultSubscriptionService->assignFreePlanToUser($user);
            $user->refresh();
            $toPlanId = $user->subscription?->plan_id;

            if ($toPlanId !== null && $toPlanId !== $fromPlanId) {
                PlanChange::create([
                    'user_id' => $user->id,
                    'from_plan_id' => $fromPlanId,
                    'to_plan_id' => $toPlanId,
                    'change_type' => 'downgrade',
                ]);

                QueueTemplatedEmailForUserJob::dispatch($user->id, 'subscription.downgrade', [
                    'planName' => $user->subscription?->planModel?->name ?? 'Free',
                ]);
            } elseif ($toPlanId === $fromPlanId || $toPlanId === null) {
                $sub->update([
                    'status' => 'past_due',
                    'current_period_end' => now(),
                ]);
            }
        }
    }
}
