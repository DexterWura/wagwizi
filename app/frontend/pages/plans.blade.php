@extends('app')

@section('title', 'Plans — ' . config('app.name'))
@section('page-id', 'plans')

@php
    $currentPlanSlug = $currentSubscription?->plan ?? null;
@endphp

@section('content')
        <main class="app-content app-content--plans">
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
                <span>Your workspace is on <strong data-app-plan-label>{{ $currentSubscription?->planModel?->name ?? 'No plan' }}</strong>.</span>
              </div>
              <p class="plan-status-msg" data-app-plan-status role="status" aria-live="polite"></p>
            </div>
          </div>

          <div class="card card--app-section">
            <div class="card__head">Choose a plan</div>
            <div class="card__body">
          <div class="plans-grid" data-app-plans>
            @foreach($plans as $plan)
            @php
                $isCurrent = $currentPlanSlug === $plan->slug;
                $price = $plan->monthly_price_cents !== null
                    ? '$' . number_format($plan->getMonthlyPriceDollars(), 0)
                    : 'Custom';
            @endphp
            <article class="plan-card{{ $isCurrent ? ' plan-card--current' : '' }}{{ $plan->slug === 'growth' ? ' plan-card--featured' : '' }}" data-plan-id="{{ $plan->slug }}">
              @if($plan->slug === 'growth')
              <span class="plan-card__badge">Popular</span>
              @endif
              <h2 class="plan-card__name">{{ $plan->name }}</h2>
              <span class="plan-card__price">{{ $price }} @if($plan->monthly_price_cents !== null)<span class="plan-card__cycle">/ month</span>@endif</span>
              @if(is_array($plan->features))
              <ul class="plan-card__list">
                @foreach($plan->features as $feature)
                <li>{{ $feature }}</li>
                @endforeach
              </ul>
              @endif
              @if($isCurrent)
              <button type="button" class="btn btn--outline" data-plan-select disabled>Current plan</button>
              @elseif($plan->slug === 'enterprise')
              <button type="button" class="btn btn--outline" data-plan-select>Contact sales</button>
              @else
              <button type="button" class="btn btn--primary" data-plan-select>Choose {{ $plan->name }}</button>
              @endif
            </article>
            @endforeach

            @if($plans->isEmpty())
            <article class="plan-card" data-plan-id="starter">
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
