<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Finishing sign in...</title>
</head>
<body>
  <p>Finishing sign in...</p>
  <script>
    (function () {
      var payload = {
        type: "social-auth-complete",
        redirectUrl: @json($redirectUrl),
      };

      try {
        if (window.opener && !window.opener.closed) {
          window.opener.postMessage(payload, window.location.origin);
        }
      } catch (e) {}

      window.close();
    })();
  </script>
</body>
</html>
