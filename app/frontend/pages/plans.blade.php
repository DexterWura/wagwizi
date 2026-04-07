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
          <div
            class="plans-grid"
            data-app-plans
            data-app-plans-server="1"
            data-current-plan-slug="{{ $currentPlanSlug ?? '' }}"
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
                $price = $currencyDisplay->formatBaseMinorForDisplay($plan->monthly_price_cents);
            @endphp
            <article class="plan-card{{ $isCurrent ? ' plan-card--current' : '' }}{{ $plan->is_most_popular ? ' plan-card--featured' : '' }}" data-plan-id="{{ $plan->slug }}" data-plan-sort="{{ $plan->sort_order }}">
              @if($plan->is_most_popular)
              <div class="plan-card__popular-strip">
                <span class="plan-card__popular-line" aria-hidden="true"></span>
                <span class="plan-card__badge-most">Most Popular</span>
              </div>
              @endif
              <h2 class="plan-card__name">{{ $plan->name }}</h2>
              <span class="plan-card__price">{{ $price }} @if($plan->monthly_price_cents !== null)<span class="plan-card__cycle">/ month</span>@endif</span>
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
            <article class="plan-card" data-plan-id="starter" data-plan-sort="0">
              <h2 class="plan-card__name">Starter</h2>
              <span class="plan-card__price">$0 <span class="plan-card__cycle">/ month</span></span>
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
