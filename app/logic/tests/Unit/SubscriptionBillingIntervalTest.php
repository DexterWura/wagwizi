<?php

namespace Tests\Unit;

use App\Models\Plan;
use App\Services\Billing\SubscriptionFulfillmentService;
use PHPUnit\Framework\TestCase;

class SubscriptionBillingIntervalTest extends TestCase
{
    private function sut(): SubscriptionFulfillmentService
    {
        return new SubscriptionFulfillmentService;
    }

    public function test_resolve_billing_interval_defaults_when_missing_or_invalid(): void
    {
        $s = $this->sut();
        $this->assertSame('monthly', $s->resolveBillingIntervalFromMeta(null));
        $this->assertSame('monthly', $s->resolveBillingIntervalFromMeta([]));
        $this->assertSame('monthly', $s->resolveBillingIntervalFromMeta(['billing_interval' => '']));
        $this->assertSame('monthly', $s->resolveBillingIntervalFromMeta(['billing_interval' => 'weekly']));
        $this->assertSame('yearly', $s->resolveBillingIntervalFromMeta(['billing_interval' => 'yearly']));
    }

    public function test_plan_charge_yearly_uses_full_yearly_cents(): void
    {
        $plan = new Plan([
            'is_free' => false,
            'is_lifetime' => false,
            'monthly_price_cents' => 1000,
            'yearly_price_cents' => 10000,
        ]);

        $this->assertSame(10000, $this->sut()->planChargeAmountCents($plan, 'yearly'));
        $this->assertSame(1000, $this->sut()->planChargeAmountCents($plan, 'monthly'));
    }

    public function test_plan_charge_monthly_falls_back_to_yearly_twelfth_when_no_monthly(): void
    {
        $plan = new Plan([
            'is_free' => false,
            'is_lifetime' => false,
            'monthly_price_cents' => null,
            'yearly_price_cents' => 12000,
        ]);

        $this->assertSame(1000, $this->sut()->planChargeAmountCents($plan, 'monthly'));
        $this->assertNull($this->sut()->planChargeAmountCents($plan, 'yearly'));
    }

    public function test_requires_online_payment_true_when_either_interval_priced(): void
    {
        $yearlyOnly = new Plan([
            'is_free' => false,
            'is_lifetime' => false,
            'monthly_price_cents' => null,
            'yearly_price_cents' => 12000,
        ]);
        $this->assertTrue($this->sut()->requiresOnlinePayment($yearlyOnly));

        $monthlyOnly = new Plan([
            'is_free' => false,
            'is_lifetime' => false,
            'monthly_price_cents' => 500,
            'yearly_price_cents' => null,
        ]);
        $this->assertTrue($this->sut()->requiresOnlinePayment($monthlyOnly));
    }
}
