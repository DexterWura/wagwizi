@extends('app')

@section('title', 'Plans — ' . config('app.name'))
@section('page-id', 'plans')

@php
    $currentPlanSlug = $currentSubscription?->plan ?? null;
    $user = auth()->user();
@endphp

@section('content')
        <main class="app-content app-content--plans">
          @if(session('success'))
            <div class="alert alert--success">{{ session('success') }}</div>
          @endif
          @if(session('info'))
            <div class="alert alert--info">{{ session('info') }}</div>
          @endif
          @if(session('error'))
            <div class="alert alert--danger">{{ session('error') }}</div>
          @endif
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true">
                  <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                </div>
                <div>
                  <h1>Plans &amp; billing</h1>
                  <p>Pick a subscription tier. Upgrade or downgrade anytime.</p>
                </div>
              </div>
            </div>
            <div class="head-actions">
              <a class="btn btn--outline" href="{{ route('plan-history') }}"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Plan history</a>
            </div>
          </div>

          <div class="card card--app-section">
            <div class="card__head">Current plan</div>
            <div class="card__body">
              <div class="plan-current-banner" role="status" aria-live="polite">
                <i class="fa-solid fa-crown" aria-hidden="true"></i>
                <span>You are currently on <strong data-app-plan-label>{{ $currentSubscription?->planModel?->name ?? 'No plan' }}</strong>.</span>
              </div>
              @php
                $cadence = $currentSubscription?->billing_interval ?? null;
                $cadencePlan = $currentSubscription?->planModel;
                $showBillingCadence = $cadence && $cadencePlan && ! $cadencePlan->is_free && ! $cadencePlan->is_lifetime;
              @endphp
              @if($showBillingCadence)
              <p class="prose-muted" style="font-size:0.88rem;margin:0.4rem 0 0;">
                <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                {{ $cadence === 'yearly' ? 'Billed yearly — renews on the anniversary of your last payment.' : 'Billed monthly — renews each month from your last payment.' }}
              </p>
              @endif
              <p class="plan-status-msg" data-app-plan-status role="status" aria-live="polite"></p>
            </div>
          </div>

          <div class="card card--app-section">
            <div class="card__head">Choose a plan</div>
            <div class="card__body">
          @if(($checkoutRequiresGatewayChoice ?? false) && !empty($availableCheckoutGateways))
            <div class="plans-pay-cards" role="radiogroup" aria-label="Payment method">
              @foreach(($availableCheckoutGateways ?? []) as $gateway)
                @php
                  [$payLabel, $payIcon] = match ($gateway) {
                    'paypal' => ['PayPal', 'fa-brands fa-paypal'],
                    'stripe' => ['Stripe', 'fa-brands fa-stripe'],
                    'paynow' => ['Paynow', 'fa-solid fa-bolt'],
                    'pesepay' => ['Pesepay', 'fa-solid fa-building-columns'],
                    default => [ucfirst($gateway), 'fa-solid fa-wallet'],
                  };
                @endphp
                <label class="plans-pay-card">
                  <input
                    type="radio"
                    name="plans_checkout_gateway"
                    class="plans-pay-card__input sr-only"
                    value="{{ $gateway }}"
                    @checked(($defaultCheckoutGateway ?? 'paynow') === $gateway)
                  />
                  <span class="plans-pay-card__surface">
                    <span class="plans-pay-card__icon" aria-hidden="true"><i class="{{ $payIcon }}"></i></span>
                    <span class="plans-pay-card__label">{{ $payLabel }}</span>
                  </span>
                </label>
              @endforeach
            </div>
          @endif
          @if($anyYearlyOffers ?? false)
            @php
              $billMonthlySelected = ($currentPlanBillingInterval ?? 'monthly') !== 'yearly';
            @endphp
            <div class="plans-billing-toolbar" style="margin-bottom: 1rem;">
              <p class="prose-muted" style="font-size:0.85rem;margin:0 0 0.5rem;">Billing period</p>
              <div class="segmented plans-billing-segmented" data-app-plans-billing role="tablist" aria-label="Billing period">
                <button type="button" role="tab" data-billing-value="monthly" aria-selected="{{ $billMonthlySelected ? 'true' : 'false' }}">Monthly</button>
                <button type="button" role="tab" data-billing-value="yearly" aria-selected="{{ $billMonthlySelected ? 'false' : 'true' }}">Yearly</button>
              </div>
            </div>
          @endif
          <div
            class="plans-grid"
            data-app-plans
            data-app-plans-server="1"
            data-current-plan-slug="{{ $currentPlanSlug ?? '' }}"
            data-billing-interval="{{ $currentPlanBillingInterval ?? 'monthly' }}"
            data-any-yearly="{{ ($anyYearlyOffers ?? false) ? '1' : '0' }}"
            data-checkout-available="{{ $paynowCheckoutAvailable ? '1' : '0' }}"
            data-checkout-mode="{{ ($checkoutRequiresGatewayChoice ?? false) ? 'choose' : 'single' }}"
            data-default-gateway="{{ $defaultCheckoutGateway ?? 'none' }}"
            data-checkout-gateway="{{ $checkoutGateway ?? 'none' }}"
            data-free-plan-slug="{{ $freePlanSlug ?? '' }}"
          >
            @foreach($plans as $plan)
            @php
                $isCurrent = $currentPlanSlug === $plan->slug;
                $sameActive = $subscriptionAccess->userHasActiveAccessToPlan($user, $plan);
                $needsRenew = $subscriptionAccess->userMustRenewSamePlan($user, $plan);
                $offersYearly = ! $plan->is_free && ! $plan->is_lifetime && $plan->yearly_price_cents !== null && (int) $plan->yearly_price_cents > 0;
                $effectiveMonthlyCents = $plan->monthly_price_cents;
                if (! $plan->is_free && ! $plan->is_lifetime) {
                    if (($effectiveMonthlyCents === null || (int) $effectiveMonthlyCents < 1) && $plan->yearly_price_cents !== null && (int) $plan->yearly_price_cents > 0) {
                        $effectiveMonthlyCents = (int) round((int) $plan->yearly_price_cents / 12);
                    }
                }
                $priceMonthlyStr = $currencyDisplay->formatBaseMinorForDisplay($plan->is_free ? 0 : $effectiveMonthlyCents);
                $priceYearlyStr = $offersYearly ? $currencyDisplay->formatBaseMinorForDisplay((int) $plan->yearly_price_cents) : '';
                $showYearlyFirst = ($anyYearlyOffers ?? false) && ($currentPlanBillingInterval ?? 'monthly') === 'yearly' && $offersYearly;
                $price = $showYearlyFirst ? $priceYearlyStr : $priceMonthlyStr;
                $cycleText = '/ month';
                if ($plan->is_free) {
                    $cycleText = '/ month';
                } elseif ($plan->is_lifetime && $plan->monthly_price_cents !== null) {
                    $cycleText = '/ month';
                } elseif ($showYearlyFirst) {
                    $cycleText = '/ year';
                } elseif (! $plan->is_lifetime && ($plan->monthly_price_cents === null || (int) $plan->monthly_price_cents < 1) && ($plan->yearly_price_cents !== null && (int) $plan->yearly_price_cents > 0)) {
                    $cycleText = '/ month';
                }
            @endphp
            <article
              class="plan-card{{ $isCurrent ? ' plan-card--current' : '' }}{{ $plan->is_most_popular ? ' plan-card--featured' : '' }}"
              data-plan-id="{{ $plan->slug }}"
              data-plan-sort="{{ $plan->sort_order }}"
              data-plan-is-free="{{ $plan->is_free ? '1' : '0' }}"
              data-plan-is-lifetime="{{ $plan->is_lifetime ? '1' : '0' }}"
              data-plan-offers-yearly="{{ $offersYearly ? '1' : '0' }}"
              data-price-monthly="{{ $priceMonthlyStr }}"
              data-price-yearly="{{ $priceYearlyStr }}"
            >
              @if($plan->is_most_popular)
              <div class="plan-card__popular-strip">
                <span class="plan-card__popular-line" aria-hidden="true"></span>
                <span class="plan-card__badge-most">Most Popular</span>
              </div>
              @endif
              <h2 class="plan-card__name">{{ $plan->name }}</h2>
              <span class="plan-card__price"><span data-plan-price-line>{{ $price }}</span>@if($plan->slug !== 'enterprise')<span class="plan-card__cycle" data-plan-cycle-line>{{ $cycleText }}</span>@endif</span>
              @if($plan->freeTrialSummary())
              <p class="plan-card__trial"><i class="fa-solid fa-gift" aria-hidden="true"></i> {{ $plan->freeTrialSummary() }}</p>
              @endif
              @if(is_array($plan->features))
              <ul class="plan-card__list">
                @foreach($plan->features as $feature)
                <li>{{ $feature }}</li>
                @endforeach
              </ul>
              @endif
              @if($needsRenew && $isCurrent)
              <button type="button" class="btn btn--primary" data-plan-select>Renew subscription</button>
              @elseif($sameActive && $isCurrent)
              <button type="button" class="btn btn--outline" data-plan-select disabled>Current plan</button>
              @elseif($plan->slug === 'enterprise')
              <button type="button" class="btn btn--outline" data-plan-select>Contact sales</button>
              @else
              <button type="button" class="btn btn--primary" data-plan-select>Choose {{ $plan->name }}</button>
              @endif
            </article>
            @endforeach

            @if($plans->isEmpty())
            <article class="plan-card" data-plan-id="starter" data-plan-sort="0" data-plan-is-free="1" data-plan-is-lifetime="0" data-plan-offers-yearly="0" data-price-monthly="$0" data-price-yearly="">
              <h2 class="plan-card__name">Starter</h2>
              <span class="plan-card__price"><span data-plan-price-line>$0</span><span class="plan-card__cycle" data-plan-cycle-line>/ month</span></span>
              <ul class="plan-card__list">
                <li>3 social profiles</li>
                <li>30 scheduled posts / month</li>
                <li>Basic analytics</li>
              </ul>
              <button type="button" class="btn btn--primary" data-plan-select>Choose Starter</button>
            </article>
            @endif
          </div>
            </div>
          </div>
        </main>
@endsection

@push('scripts')
    <script>
      window.__paidPlanSlugs = @json($paidPlanSlugs ?? []);
    </script>
@endpush
