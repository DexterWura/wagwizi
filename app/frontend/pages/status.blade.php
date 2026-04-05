<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script>
      (function () {
        var t = localStorage.getItem("app-theme") || localStorage.getItem("creem-clone-theme");
        if (t !== "light" && t !== "dark") {
          t = window.matchMedia && window.matchMedia("(prefers-color-scheme: light)").matches ? "light" : "dark";
        }
        document.documentElement.setAttribute("data-theme", t);
      })();
    </script>
    @include('seo-meta', [
      'seoTitleOverride' => 'System Status — ' . config('app.name'),
      'seoDescriptionOverride' => 'Live service health and uptime for ' . config('app.name') . '.',
      'seoCanonicalOverride' => route('status'),
      'seoTypeOverride' => 'website',
      'seoRobotsOverride' => 'index,follow',
    ])
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="{{ asset(config('app.debug') ? 'assets/css/style.css' : 'assets/css/style.min.css') }}" />
  </head>
  <body class="error-page">
    <div class="error-page__container status-page">

      <div class="error-page__card">
        <div class="error-page__icon error-page__icon--{{ $healthy ? 'healthy' : 'outage' }}">
          <i class="fa-solid {{ $healthy ? 'fa-circle-check' : 'fa-triangle-exclamation' }}" aria-hidden="true"></i>
        </div>

        <h1 class="error-page__title">
          {{ config('app.name') }} {{ $healthy ? 'is Live' : 'is Down' }}
        </h1>

        <p class="error-page__message">
          {{ $healthy ? 'Service is available.' : 'Service is currently unavailable. Please try again shortly.' }}
        </p>

        <div class="error-page__actions">
          <a class="btn btn--primary" href="{{ url('/') }}"><i class="fa-solid fa-house" aria-hidden="true"></i> Go home</a>
          <a class="btn btn--outline" href="{{ route('status') }}"><i class="fa-solid fa-rotate-right" aria-hidden="true"></i> Refresh</a>
        </div>
      </div>

      <div class="error-page__brand">
        <span class="sr-only">{{ config('app.name') }}</span>
        @include('brand-logo')
      </div>

    </div>

    <button type="button" class="app-theme-toggle" data-app-theme-toggle aria-label="Switch color theme">
      <i class="fa-solid fa-moon" data-app-theme-icon aria-hidden="true"></i>
      <span class="app-theme-toggle__label" data-app-theme-label>Dark</span>
    </button>

    <script>
      (function () {
        var t = document.documentElement.getAttribute("data-theme") || "dark";
        var icon = document.querySelector("[data-app-theme-icon]");
        if (icon) icon.className = t === "light" ? "fa-solid fa-sun" : "fa-solid fa-moon";
      })();
    </script>
    <script src="{{ asset($appJsAsset = (config('app.debug') ? 'assets/js/app.js' : 'assets/js/app.min.js')) }}?v={{ file_exists(public_path($appJsAsset)) ? filemtime(public_path($appJsAsset)) : time() }}"></script>
  </body>
</html>
