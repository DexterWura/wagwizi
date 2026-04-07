<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Under construction — {{ config('app.name') }}</title>
  <style>
    :root {
      color-scheme: light dark;
      --bg: #0f1419;
      --fg: #e7ecf1;
      --muted: #8b98a5;
      --accent: #1d9bf0;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, sans-serif;
      background: var(--bg);
      color: var(--fg);
      line-height: 1.5;
    }
    .panel {
      max-width: 28rem;
      text-align: center;
    }
    h1 {
      font-size: 1.35rem;
      font-weight: 600;
      margin: 0 0 0.75rem;
    }
    p {
      margin: 0 0 1.25rem;
      color: var(--muted);
      font-size: 0.95rem;
    }
    a {
      color: var(--accent);
      text-decoration: none;
      font-weight: 500;
    }
    a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="panel">
    <h1>We&rsquo;re updating things</h1>
    <p>This site is temporarily in maintenance mode. Please check back soon.</p>
    <p><a href="{{ route('login') }}">Administrator sign in</a></p>
  </div>
</body>
</html>
