<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Finishing connection...</title>
</head>
<body>
  <p>Finishing connection...</p>
  <script>
    (function () {
      var payload = {
        type: "oauth-connect-complete",
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
