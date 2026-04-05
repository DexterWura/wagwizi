<?php

declare(strict_types=1);

namespace App\Services\Affiliate;

use App\Models\AffiliateCommission;
use App\Models\PaymentTransaction;
use App\Models\SiteSetting;
use App\Models\User;

final class AffiliateCommissionService
{
    public function maybeAwardFirstSubscriptionCommission(User $referredUser, PaymentTransaction $transaction): void
    {
        if (! $this->isAffiliateEnabled()) {
            return;
        }

        $referrerId = (int) ($referredUser->referred_by_user_id ?? 0);
        if ($referrerId < 1 || $referrerId === (int) $referredUser->id) {
            return;
        }

        if ((int) $transaction->amount_cents < 1) {
            return;
        }

        if (AffiliateCommission::query()->where('referred_user_id', $referredUser->id)->exists()) {
            return;
        }

        $percent = $this->firstSubscriptionPercent();
        if ($percent <= 0) {
            return;
        }

        $commissionCents = (int) round(((int) $transaction->amount_cents * $percent) / 100);
        if ($commissionCents < 1) {
            return;
        }

        AffiliateCommission::create([
            'referrer_user_id' => $referrerId,
            'referred_user_id' => (int) $referredUser->id,
            'payment_transaction_id' => (int) $transaction->id,
            'base_amount_cents' => (int) $transaction->amount_cents,
            'commission_amount_cents' => $commissionCents,
            'currency' => (string) $transaction->currency,
            'commission_percent' => $percent,
            'status' => 'pending',
            'awarded_at' => now(),
        ]);
    }

    public function isAffiliateEnabled(): bool
    {
        return SiteSetting::get('affiliate_program_enabled', '0') === '1';
    }

    public function firstSubscriptionPercent(): float
    {
        $raw = (string) SiteSetting::get('affiliate_first_subscription_percent', '10.00');
        $value = (float) $raw;

        return max(0.0, min(100.0, $value));
    }
}

