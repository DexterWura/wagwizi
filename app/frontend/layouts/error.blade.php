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
    <title>@yield('title', config('app.name'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}" />
  </head>
  <body class="error-page">
    <div class="error-page__container">
      <div class="error-page__card">
        <div class="error-page__icon error-page__icon--@yield('icon-variant', 'default')">
          <i class="@yield('icon', 'fa-solid fa-triangle-exclamation')" aria-hidden="true"></i>
        </div>
        <span class="error-page__code">@yield('code')</span>
        <h1 class="error-page__title">@yield('heading')</h1>
        <p class="error-page__message">@yield('message')</p>
        <div class="error-page__actions">
          @yield('actions')
        </div>
        <p class="error-page__status-link">
          <a href="{{ route('status') }}"><i class="fa-solid fa-signal" aria-hidden="true"></i> Check system status</a>
        </p>
      </div>
      <div class="error-page__brand">
        <span class="sr-only">{{ config('app.name') }}</span>
        @include('brand-logo')
      </div>
    </div>

    <script src="{{ asset('assets/js/app.js') }}"></script>
  </body>
</html>
