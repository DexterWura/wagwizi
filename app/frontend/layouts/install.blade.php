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
    <title>@yield('title', 'Install')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="{{ asset(config('app.debug') ? 'assets/css/style.css' : 'assets/css/style.min.css') }}" />
  </head>
  <body class="login-page">
    <div class="login-page__brand">
      <span class="sr-only">{{ config('app.name') }}</span>
      @include('brand-logo')
    </div>

    <div class="installer">
      <div class="installer__steps">
        @php $current = View::yieldContent('step', '1'); @endphp
        <div class="installer__step {{ $current >= 1 ? 'installer__step--active' : '' }} {{ $current > 1 ? 'installer__step--done' : '' }}">
          <span class="installer__step-num">{{ $current > 1 ? '✓' : '1' }}</span>
          <span>Requirements</span>
        </div>
        <div class="installer__step-line {{ $current > 1 ? 'installer__step-line--done' : '' }}"></div>
        <div class="installer__step {{ $current >= 2 ? 'installer__step--active' : '' }} {{ $current > 2 ? 'installer__step--done' : '' }}">
          <span class="installer__step-num">{{ $current > 2 ? '✓' : '2' }}</span>
          <span>Database</span>
        </div>
        <div class="installer__step-line {{ $current > 2 ? 'installer__step-line--done' : '' }}"></div>
        <div class="installer__step {{ $current >= 3 ? 'installer__step--active' : '' }} {{ $current > 3 ? 'installer__step--done' : '' }}">
          <span class="installer__step-num">{{ $current > 3 ? '✓' : '3' }}</span>
          <span>Admin</span>
        </div>
        <div class="installer__step-line {{ $current > 3 ? 'installer__step-line--done' : '' }}"></div>
        <div class="installer__step {{ $current >= 4 ? 'installer__step--active' : '' }}">
          <span class="installer__step-num">{{ $current > 4 ? '✓' : '4' }}</span>
          <span>Done</span>
        </div>
      </div>

      <div class="installer__panel">
        @yield('content')
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
    <script src="{{ asset(config('app.debug') ? 'assets/js/app.js' : 'assets/js/app.min.js') }}"></script>
  </body>
</html>
