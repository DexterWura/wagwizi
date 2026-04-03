<div class="app-subscription-renewal-banner" role="status">
  <div class="app-subscription-renewal-banner__inner">
    <p class="app-subscription-renewal-banner__text">
      <i class="fa-solid fa-calendar-day" aria-hidden="true"></i>
      @if(($subscriptionRenewalDaysLeft ?? 8) === 0)
        Your current paid period <strong>ends today</strong>. Renew or change plan to avoid interruption.
      @elseif(($subscriptionRenewalDaysLeft ?? 0) === 1)
        Your paid plan period renews in <strong>1 day</strong>. Review billing if needed.
      @else
        Your paid plan period renews in <strong>{{ $subscriptionRenewalDaysLeft }} days</strong>. Review billing on the plans page.
      @endif
    </p>
    <a class="btn btn--outline btn--compact" href="{{ route('plans') }}">Plans &amp; billing</a>
  </div>
</div>
