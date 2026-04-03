<div class="app-trial-ended-banner" role="alert">
  <div class="app-trial-ended-banner__inner">
    <p class="app-trial-ended-banner__text">
      <i class="fa-solid fa-hourglass-end" aria-hidden="true"></i>
      Your trial has ended. Subscribe to keep this plan
      @if($freePlanSlug)
        or switch to the free plan on the plans page.
      @else
        to continue.
      @endif
    </p>
    <div class="app-trial-ended-banner__actions">
      <a class="btn btn--primary btn--compact" href="{{ route('plans') }}">View plans</a>
    </div>
  </div>
</div>
