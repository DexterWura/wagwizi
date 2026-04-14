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
          t = "light";
        }
        document.documentElement.setAttribute("data-theme", t);
      })();
    </script>
    @include('seo-meta')
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="{{ asset(app_bundle_css_path()) }}?v={{ app_bundle_asset_version(app_bundle_css_path()) }}" />
  </head>
  <body class="legal-page">
    <header class="legal-page__header">
      <a class="legal-page__logo" href="{{ route('landing') }}" aria-label="{{ config('app.name') }} home">
        @include('brand-logo')
      </a>
    </header>
    <main class="legal-page__main" id="main">
      <article class="legal-page__article">
        @yield('content')
      </article>
    </main>
    <footer class="legal-page__footer" role="contentinfo">
      <a href="{{ route('terms') }}">Terms of Service</a>
      <span class="legal-page__footer-sep" aria-hidden="true">·</span>
      <a href="{{ route('privacy') }}">Privacy Policy</a>
      <span class="legal-page__footer-sep" aria-hidden="true">·</span>
      <a href="{{ route('landing') }}">Home</a>
    </footer>
    <script src="{{ asset(app_bundle_js_path()) }}?v={{ app_bundle_asset_version(app_bundle_js_path()) }}"></script>
  </body>
</html>
