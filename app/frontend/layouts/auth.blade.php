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
    @include('seo-meta', ['seoRobotsOverride' => 'noindex,nofollow'])
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="{{ asset(config('app.debug') ? 'assets/css/style.css' : 'assets/css/style.min.css') }}" />
  </head>
  <body class="login-page">
    @yield('content')

    <footer class="auth-page-footer" role="contentinfo">
      <a href="{{ route('terms') }}">Terms of Service</a>
      <span class="auth-page-footer__sep" aria-hidden="true">·</span>
      <a href="{{ route('privacy') }}">Privacy Policy</a>
    </footer>

    <script src="{{ asset($appJsAsset = (config('app.debug') ? 'assets/js/app.js' : 'assets/js/app.min.js')) }}?v={{ file_exists(public_path($appJsAsset)) ? filemtime(public_path($appJsAsset)) : time() }}"></script>
  </body>
</html>
