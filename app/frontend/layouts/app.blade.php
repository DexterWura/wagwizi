<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
      (function () {
        var t = localStorage.getItem("app-theme") || localStorage.getItem("creem-clone-theme");
        if (t !== "light" && t !== "dark") {
          t = window.matchMedia && window.matchMedia("(prefers-color-scheme: light)").matches ? "light" : "dark";
        }
        document.documentElement.setAttribute("data-theme", t);
      })();
    </script>
    <script>
      (function () {
        try {
          var pending = sessionStorage.getItem("appNavPending");
          var rawAt = sessionStorage.getItem("appNavPendingAt");
          var pendingAt = rawAt ? parseInt(rawAt, 10) : NaN;
          var fresh = pending && !isNaN(pendingAt) && (Date.now() - pendingAt) <= 15000;

          if (fresh) {
            document.documentElement.classList.add("app-nav-loading");
          } else {
            sessionStorage.removeItem("appNavPending");
            sessionStorage.removeItem("appNavPendingAt");
          }
        } catch (e) {}
      })();
    </script>
    @include('seo-meta', ['seoRobotsOverride' => 'noindex,nofollow'])
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    @php
      $fontCss = 'https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap';
      $faCss = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css';
    @endphp
    <link rel="preload" href="{{ $fontCss }}" as="style" />
    <link href="{{ $fontCss }}" rel="stylesheet" media="print" onload="this.media='all'" />
    <noscript><link href="{{ $fontCss }}" rel="stylesheet" /></noscript>
    <link rel="preload" href="{{ $faCss }}" as="style" crossorigin="anonymous" />
    <link href="{{ $faCss }}" rel="stylesheet" media="print" onload="this.media='all'" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <noscript><link href="{{ $faCss }}" rel="stylesheet" crossorigin="anonymous" referrerpolicy="no-referrer" /></noscript>
    <link rel="stylesheet" href="{{ asset(config('app.debug') ? 'assets/css/style.css' : 'assets/css/style.min.css') }}" />
    @stack('styles')
  </head>
  <body class="app" data-app-page="@yield('page-id')" @if(!empty($aiClientConfig)) data-app-ai-config="{{ e(json_encode($aiClientConfig)) }}" @endif>
    <div class="app-nav-preloader" id="app-nav-preloader" role="status" aria-live="polite" aria-hidden="true">
      <div class="app-nav-preloader__inner">
        <div class="app-nav-preloader__orbit" aria-hidden="true"></div>
        <span class="app-nav-preloader__text">Loading…</span>
      </div>
    </div>
    <div class="app-overlay" id="app-drawer-overlay" data-app-drawer-overlay aria-hidden="true"></div>
    <div class="app-shell">
      @include('app-sidebar', ['activePage' => View::yieldContent('page-id')])

      <div class="app-main">
        @section('topbar')
        @include('app-topbar')
        @if(($currentUser ?? null) && ($showTrialEndedBanner ?? false))
        @include('trial-ended-banner')
        @elseif(($currentUser ?? null) && ($showSubscriptionRenewalBanner ?? false))
        @include('subscription-renewal-banner')
        @endif
        @show

        @yield('content')

        <footer class="app-shell-footer" role="contentinfo">
          <a href="{{ route('terms') }}">Terms of Service</a>
          <span class="app-shell-footer__sep" aria-hidden="true">·</span>
          <a href="{{ route('privacy') }}">Privacy Policy</a>
        </footer>
      </div>
    </div>

    @include('modal-notifications')
    @stack('modals')
    @if($showFloatingHelp ?? true)
    @include('modal-help-ticket')

    <button type="button" class="app-fab" data-app-modal-open="modal-help-ticket" aria-label="Get help — create support ticket" title="Help and support">
      <span class="app-fab__avatar" aria-hidden="true"><i class="fa-solid fa-robot"></i></span>
      Get Help
    </button>
    @endif

    <script>
      (function () {
        var t = document.documentElement.getAttribute("data-theme") || "dark";
        var icon = document.querySelector("[data-app-theme-icon]");
        if (icon) icon.className = t === "light" ? "fa-solid fa-sun" : "fa-solid fa-moon";
      })();
    </script>
    <script>
      window.__appDisplayTimezones = @json($displayTimezonesMeta ?? []);
      window.__appDefaultDisplayTimezone = @json($defaultDisplayTimezoneIdentifier ?? 'UTC');
    </script>
    <script src="{{ asset($appJsAsset = (config('app.debug') ? 'assets/js/app.js' : 'assets/js/app.min.js')) }}?v={{ file_exists(public_path($appJsAsset)) ? filemtime(public_path($appJsAsset)) : time() }}"></script>
    @stack('scripts')
  </body>
</html>
