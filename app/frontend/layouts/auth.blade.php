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
    <link rel="stylesheet" href="{{ asset(app_bundle_css_path()) }}?v={{ app_bundle_asset_version(app_bundle_css_path()) }}" />
  </head>
  <body class="login-page">
    @yield('content')

    <footer class="auth-page-footer" role="contentinfo">
      <a href="{{ route('terms') }}">Terms of Service</a>
      <span class="auth-page-footer__sep" aria-hidden="true">·</span>
      <a href="{{ route('privacy') }}">Privacy Policy</a>
    </footer>

    <script src="{{ asset(app_bundle_js_path()) }}?v={{ app_bundle_asset_version(app_bundle_js_path()) }}"></script>
  </body>
</html>
