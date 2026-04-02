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
          if (sessionStorage.getItem("appNavPending")) {
            document.documentElement.classList.add("app-nav-loading");
          }
        } catch (e) {}
      })();
    </script>
    <title>@yield('title', config('app.name'))</title>
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}" />
    @stack('styles')
  </head>
  <body class="app" data-app-page="@yield('page-id')">
    <div class="app-overlay" id="app-drawer-overlay" data-app-drawer-overlay aria-hidden="true"></div>
    <div class="app-shell">
      @include('app-sidebar', ['activePage' => View::yieldContent('page-id')])

      <div class="app-main">
        <div class="app-nav-preloader" id="app-nav-preloader" role="status" aria-live="polite" aria-hidden="true">
          <div class="app-nav-preloader__inner">
            <div class="app-nav-preloader__orbit" aria-hidden="true"></div>
            <span class="app-nav-preloader__text">Loading…</span>
          </div>
        </div>
        @section('topbar')
        @include('app-topbar')
        @show

        @yield('content')
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
    <script src="{{ asset('assets/js/app.js') }}"></script>
    @stack('scripts')
  </body>
</html>
