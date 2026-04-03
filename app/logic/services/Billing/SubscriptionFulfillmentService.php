<?php

namespace App\Services\Billing;

use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\PlanChange;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SubscriptionFulfillmentService
{
    /**
     * Activates a paid plan after a successful payment (Paynow, etc.).
     */
    public function fulfillAfterPayment(User $user, Plan $newPlan, PaymentTransaction $transaction): Subscription
    {
        return DB::transaction(function () use ($user, $newPlan, $transaction) {
            $locked = PaymentTransaction::query()->whereKey($transaction->id)->lockForUpdate()->first();
            if ($locked === null || $locked->isCompleted()) {
                return $user->subscription()->firstOrFail();
            }

            $oldSub    = $user->subscription;
            $oldPlanId = $oldSub?->plan_id;

            if ($newPlan->is_lifetime && $newPlan->hasReachedLifetimeCap()) {
                throw new \RuntimeException('Lifetime plan is full.');
            }

            $subscription = Subscription::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'plan_id'                 => $newPlan->id,
                    'plan'                    => $newPlan->slug,
                    'gateway'                 => $transaction->gateway,
                    'gateway_subscription_id' => $transaction->reference,
                    'status'                  => 'active',
                    'current_period_start'    => now(),
                    'current_period_end'      => $newPlan->is_lifetime ? null : now()->addMonth(),
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
                    'user_id'          => $user->id,
                    'from_plan_id'     => $oldPlanId,
                    'to_plan_id'       => $newPlan->id,
                    'change_type'      => 'upgrade',
                    'gateway'          => $transaction->gateway,
                    'gateway_event_id' => $transaction->paynow_reference ?? $transaction->reference,
                ]);
            }

            $locked->update(['status' => 'completed']);

            return $subscription;
        });
    }

    public function planChargeAmountCents(Plan $plan): ?int
    {
        if ($plan->is_free) {
            return null;
        }

        if ($plan->monthly_price_cents !== null && $plan->monthly_price_cents > 0) {
            return (int) $plan->monthly_price_cents;
        }

        if ($plan->yearly_price_cents !== null && $plan->yearly_price_cents > 0) {
            return (int) round($plan->yearly_price_cents / 12);
        }

        return null;
    }

    public function requiresOnlinePayment(Plan $plan): bool
    {
        return ! $plan->is_free && $this->planChargeAmountCents($plan) !== null;
    }
}
