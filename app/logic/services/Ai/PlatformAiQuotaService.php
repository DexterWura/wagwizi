<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tracks remaining platform (admin API key) tokens per subscription billing period.
 * BYOK requests never touch this balance.
 *
 * Platform usage uses reserve → outbound HTTP → finalize so concurrent requests cannot
 * overspend the balance before the row is locked.
 */
final class PlatformAiQuotaService
{
    public function applyPlanBudgetToSubscription(Subscription $subscription, ?Plan $plan = null): void
    {
        $plan ??= $subscription->planModel;
        if ($plan === null) {
            $subscription->forceFill(['platform_ai_tokens_remaining' => 0])->save();
            $this->invalidateLayoutSummaryCache((int) $subscription->user_id);

            return;
        }

        $budget = max(0, (int) $plan->platform_ai_tokens_per_period);
        $subscription->forceFill(['platform_ai_tokens_remaining' => $budget])->save();
        $this->invalidateLayoutSummaryCache((int) $subscription->user_id);
    }

    /**
     * Full refill when the plan changes or a new paid period is recorded (checkout fulfillment, direct plan change, trial start).
     */
    public function refreshForUserSubscription(User $user): void
    {
        $sub = $user->subscription;
        if ($sub === null) {
            return;
        }

        $sub->loadMissing('planModel');
        $this->applyPlanBudgetToSubscription($sub, $sub->planModel);
    }

    public function planIncludesPlatformAiTokens(?Plan $plan): bool
    {
        return $plan !== null && (int) $plan->platform_ai_tokens_per_period > 0;
    }

    /**
     * True when the user is on platform AI, paid, and has exhausted their plan token balance (not super-admin, not BYOK).
     */
    public function isPlatformAiQuotaExhausted(User $user): bool
    {
        if ($user->isSuperAdmin() || $user->usesComposerAiByok()) {
            return false;
        }

        if (($user->ai_source ?? 'platform') !== 'platform') {
            return false;
        }

        if (! $user->hasPaidActiveSubscriptionForAi()) {
            return false;
        }

        if (! app(PlatformAiConfigService::class)->isConfigured()) {
            return false;
        }

        $user->loadMissing('subscription.planModel');
        $sub  = $user->subscription;
        $plan = $sub?->planModel;

        if ($sub === null || ! $this->planIncludesPlatformAiTokens($plan)) {
            return false;
        }

        return (int) $sub->platform_ai_tokens_remaining <= 0;
    }

    /**
     * Paid platform user whose plan allocates zero platform tokens (must use BYOK for AI).
     */
    public function isPlatformAiDisabledOnPlan(User $user): bool
    {
        if ($user->isSuperAdmin() || $user->usesComposerAiByok()) {
            return false;
        }

        if (($user->ai_source ?? 'platform') !== 'platform') {
            return false;
        }

        if (! $user->hasPaidActiveSubscriptionForAi()) {
            return false;
        }

        if (! app(PlatformAiConfigService::class)->isConfigured()) {
            return false;
        }

        $user->loadMissing('subscription.planModel');

        return ! $this->planIncludesPlatformAiTokens($user->subscription?->planModel);
    }

    /**
     * Lock subscription row and hold up to min(remaining, cap) tokens before the provider HTTP call.
     *
     * @throws PlatformAiPlanHasNoTokensException
     * @throws PlatformAiQuotaExceededException
     */
    public function reservePlatformTokens(User $user): int
    {
        if ($user->isSuperAdmin()) {
            return 0;
        }

        $cap = max(1, (int) config('ai.platform.max_reserve_tokens_per_request', 16000));

        $reserved = (int) DB::transaction(function () use ($user, $cap): int {
            $sub = Subscription::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($sub === null) {
                throw new PlatformAiPlanHasNoTokensException('No active subscription for platform AI.');
            }

            $sub->loadMissing('planModel');
            $plan = $sub->planModel;

            if (! $this->planIncludesPlatformAiTokens($plan)) {
                throw new PlatformAiPlanHasNoTokensException('This plan does not include platform AI credits. Add your own API key under Settings → AI, or choose a plan that includes credits.');
            }

            $remaining = (int) $sub->platform_ai_tokens_remaining;
            if ($remaining <= 0) {
                throw new PlatformAiQuotaExceededException(
                    'You have used all platform AI credits for this billing period. Wait until your plan renews, or use your own API key under Settings → AI.'
                );
            }

            $toReserve = min($remaining, $cap);
            $sub->forceFill([
                'platform_ai_tokens_remaining' => $remaining - $toReserve,
            ])->save();

            return $toReserve;
        });
        $this->invalidateLayoutSummaryCache((int) $user->id);

        return $reserved;
    }

    /**
     * After the provider responds: balance becomes (post-reserve remaining) + reserved − actual usage (floored at 0).
     */
    public function finalizePlatformTokenReservation(User $user, int $reserved, int $actualTokensUsed): void
    {
        if ($user->isSuperAdmin() || $reserved <= 0) {
            return;
        }

        $actualTokensUsed = max(0, $actualTokensUsed);

        DB::transaction(function () use ($user, $reserved, $actualTokensUsed): void {
            $sub = Subscription::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($sub === null) {
                return;
            }

            $current = (int) $sub->platform_ai_tokens_remaining;
            $sub->forceFill([
                'platform_ai_tokens_remaining' => max(0, $current + $reserved - $actualTokensUsed),
            ])->save();
        });
        $this->invalidateLayoutSummaryCache((int) $user->id);
    }

    public function invalidateLayoutSummaryCache(int $userId): void
    {
        Cache::forget('layout:ai_quota:v1:' . $userId);
    }

    /**
     * @return array{remaining: int, budget: int, applies: bool}
     */
    public function summaryForLayout(User $user): array
    {
        $cacheKey = 'layout:ai_quota:v1:' . $user->id;

        return Cache::remember($cacheKey, 45, function () use ($user): array {
            $user->loadMissing('subscription.planModel');
            $sub  = $user->subscription;
            $plan = $sub?->planModel;

            $applies = ! $user->isSuperAdmin()
                && ! $user->usesComposerAiByok()
                && ($user->ai_source ?? 'platform') === 'platform'
                && $user->hasPaidActiveSubscriptionForAi()
                && app(PlatformAiConfigService::class)->isConfigured()
                && $this->planIncludesPlatformAiTokens($plan);

            $budget = $plan !== null ? max(0, (int) $plan->platform_ai_tokens_per_period) : 0;
            $remaining = $sub !== null ? max(0, (int) $sub->platform_ai_tokens_remaining) : 0;

            return [
                'remaining' => $remaining,
                'budget'    => $budget,
                'applies'   => $applies,
            ];
        });
    }
}
