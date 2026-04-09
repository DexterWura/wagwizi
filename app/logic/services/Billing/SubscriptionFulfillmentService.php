<?php

namespace App\Services\Billing;

use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\PlanChange;
use App\Models\Subscription;
use App\Models\User;
use App\Jobs\QueueTemplatedEmailForUserJob;
use App\Services\Affiliate\AffiliateCommissionService;
use App\Services\Ai\PlatformAiQuotaService;
use App\Services\Notifications\InAppNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

            $hasAnyCompletedPaymentsBefore = PaymentTransaction::query()
                ->where('user_id', $user->id)
                ->where('status', 'completed')
                ->exists();

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
                    'trial_ends_at'           => null,
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
                    'user_id'          => $user->id,
                    'from_plan_id'     => $oldPlanId,
                    'to_plan_id'       => $newPlan->id,
                    'change_type'      => 'upgrade',
                    'gateway'          => $transaction->gateway,
                    'gateway_event_id' => $transaction->paynow_reference ?? $transaction->reference,
                ]);
            }

            $locked->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            Log::info('Paid plan fulfilled', [
                'user_id' => $user->id,
                'transaction_id' => $locked->id,
                'gateway' => $locked->gateway,
                'reference' => $locked->reference,
                'from_plan_id' => $oldPlanId,
                'to_plan_id' => $newPlan->id,
                'to_plan_slug' => $newPlan->slug,
                'subscription_id' => $subscription->id,
                'renewal' => ($oldPlanId === $newPlan->id) && $hasAnyCompletedPaymentsBefore,
            ]);

            // Affiliate payout applies only to the referred user's first successful paid subscription.
            if (! $hasAnyCompletedPaymentsBefore) {
                app(AffiliateCommissionService::class)->maybeAwardFirstSubscriptionCommission($user, $locked);
            }

            if ($oldPlanId !== $newPlan->id) {
                $oldPlan = $oldPlanId ? Plan::find($oldPlanId) : null;
                DB::afterCommit(function () use ($user, $newPlan, $oldPlan): void {
                    $templateKey = ($oldPlan && ! $oldPlan->is_free && $newPlan->is_free)
                        ? 'subscription.downgrade'
                        : 'subscription.updated';

                    QueueTemplatedEmailForUserJob::dispatch($user->id, $templateKey, [
                        'planName'         => $newPlan->name,
                        'previousPlanName' => $oldPlan?->name ?? '',
                    ]);
                });
            }

            if (! $newPlan->is_free) {
                $isRenewal = ($oldPlanId === $newPlan->id) && $hasAnyCompletedPaymentsBefore;
                DB::afterCommit(function () use ($user, $newPlan, $isRenewal, $oldPlanId): void {
                    try {
                        $inApp = app(InAppNotificationService::class);
                        if ($isRenewal) {
                            $inApp->notifySuperAdminsSubscriptionRenewal($user, $newPlan);
                        } elseif ($oldPlanId !== $newPlan->id) {
                            $inApp->notifySuperAdminsNewSubscription($user, $newPlan);
                        }
                        $inApp->emailSuperAdminsPaidSubscription($user, $newPlan, $isRenewal);
                    } catch (\Throwable) {
                    }
                });
            }

            return $subscription;
        });
    }

    public function planChargeAmountCents(Plan $plan): ?int
    {
        if ($plan->is_free) {
            return null;
        }

        if ($plan->isOneTimePurchase()) {
            if ($plan->monthly_price_cents !== null && $plan->monthly_price_cents > 0) {
                return (int) $plan->monthly_price_cents;
            }
            if ($plan->yearly_price_cents !== null && $plan->yearly_price_cents > 0) {
                return (int) $plan->yearly_price_cents;
            }

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

    /**
     * Line item title for hosted checkouts (Stripe, PayPal, Paynow, etc.).
     */
    public function planCheckoutProductTitle(Plan $plan): string
    {
        if ($plan->is_free) {
            return $plan->name;
        }
        if ($plan->isOneTimePurchase()) {
            return $plan->name . ' (lifetime — one-time payment)';
        }

        return $plan->name . ' subscription';
    }

    public function requiresOnlinePayment(Plan $plan): bool
    {
        return ! $plan->is_free && $this->planChargeAmountCents($plan) !== null;
    }
}
