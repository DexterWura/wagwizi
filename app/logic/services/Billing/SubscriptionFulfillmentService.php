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
use App\Services\Subscription\DefaultSubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class SubscriptionFulfillmentService
{
    public function __construct(
        private DefaultSubscriptionService $defaultSubscriptionService
    ) {}

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

            $interval = $this->resolveBillingIntervalFromMeta($locked->meta);
            $periodEnd = null;
            $billingIntervalCol = null;
            if (! $newPlan->is_lifetime) {
                $billingIntervalCol = $interval;
                $periodEnd = $interval === 'yearly' ? now()->addYear() : now()->addMonth();
            }

            $subscription = Subscription::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'plan_id'                 => $newPlan->id,
                    'plan'                    => $newPlan->slug,
                    'billing_interval'        => $billingIntervalCol,
                    'gateway'                 => $transaction->gateway,
                    'gateway_subscription_id' => $transaction->reference,
                    'status'                  => 'active',
                    'current_period_start'    => now(),
                    'current_period_end'      => $periodEnd,
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

    /**
     * Normalizes payment transaction meta for post-payment fulfillment (defaults to monthly).
     *
     * @return 'monthly'|'yearly'
     */
    public function resolveBillingIntervalFromMeta(mixed $meta): string
    {
        $arr = is_array($meta) ? $meta : [];

        return (($arr['billing_interval'] ?? 'monthly') === 'yearly') ? 'yearly' : 'monthly';
    }

    /**
     * @param 'monthly'|'yearly' $billingInterval Recurring plans: charge monthly total or full annual amount. Lifetime ignores this.
     */
    public function planChargeAmountCents(Plan $plan, string $billingInterval = 'monthly'): ?int
    {
        $billingInterval = $billingInterval === 'yearly' ? 'yearly' : 'monthly';

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

        if ($billingInterval === 'yearly') {
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
     *
     * @param 'monthly'|'yearly' $billingInterval
     */
    public function planCheckoutProductTitle(Plan $plan, string $billingInterval = 'monthly'): string
    {
        if ($plan->is_free) {
            return $plan->name;
        }
        if ($plan->isOneTimePurchase()) {
            return $plan->name . ' (lifetime — one-time payment)';
        }

        $billingInterval = $billingInterval === 'yearly' ? 'yearly' : 'monthly';
        if ($billingInterval === 'yearly') {
            return $plan->name . ' subscription (annual billing)';
        }

        return $plan->name . ' subscription';
    }

    public function requiresOnlinePayment(Plan $plan): bool
    {
        if ($plan->is_free) {
            return false;
        }

        if ($plan->isOneTimePurchase()) {
            return $this->planChargeAmountCents($plan) !== null;
        }

        return $this->planChargeAmountCents($plan, 'monthly') !== null
            || $this->planChargeAmountCents($plan, 'yearly') !== null;
    }

    public function reverseAfterPayment(PaymentTransaction $transaction, string $reason, ?string $gatewayEventId = null): bool
    {
        return DB::transaction(function () use ($transaction, $reason, $gatewayEventId): bool {
            $locked = PaymentTransaction::query()->whereKey($transaction->id)->lockForUpdate()->first();
            if ($locked === null || ! $locked->isCompleted()) {
                return false;
            }

            $user = $locked->user;
            $oldSub = $user->subscription;
            $oldPlanId = $oldSub?->plan_id;

            $meta = is_array($locked->meta) ? $locked->meta : [];
            $meta['reversal_reason'] = $reason;
            $meta['reversed_at'] = now()->toIso8601String();
            if (is_string($gatewayEventId) && $gatewayEventId !== '') {
                $meta['reversal_event_id'] = $gatewayEventId;
            }

            $locked->update([
                'status' => 'reversed',
                'failed_at' => now(),
                'failure_message' => $reason,
                'meta' => $meta,
            ]);

            $this->defaultSubscriptionService->assignFreePlanToUser($user);
            $user->refresh();
            $toPlanId = $user->subscription?->plan_id;

            if ($oldPlanId !== null && $toPlanId !== null && $oldPlanId !== $toPlanId) {
                PlanChange::create([
                    'user_id' => $user->id,
                    'from_plan_id' => $oldPlanId,
                    'to_plan_id' => $toPlanId,
                    'change_type' => 'downgrade',
                    'gateway' => $locked->gateway,
                    'gateway_event_id' => $gatewayEventId ?: $locked->reference,
                ]);
            }

            Log::warning('Paid plan reversed and downgraded', [
                'transaction_id' => $locked->id,
                'gateway' => $locked->gateway,
                'reference' => $locked->reference,
                'user_id' => $locked->user_id,
                'plan_id' => $locked->plan_id,
                'reason' => $reason,
                'gateway_event_id' => $gatewayEventId,
            ]);

            return true;
        });
    }
}
