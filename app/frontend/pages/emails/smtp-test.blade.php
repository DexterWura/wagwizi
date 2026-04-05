<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SMTP Test</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;color:#0f172a;font-family:Arial,sans-serif;">
  <div style="max-width:620px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;">
    <h1 style="margin:0 0 12px;font-size:22px;line-height:1.2;">SMTP test email successful</h1>
    <p style="margin:0 0 8px;font-size:15px;line-height:1.6;">
      Hello {{ $user->name }}, your notification SMTP settings are working.
    </p>
    <p style="margin:0;font-size:13px;line-height:1.6;color:#64748b;">
      Sent at {{ $sentAt->format('M j, Y g:i A T') }} from {{ config('app.name') }}.
    </p>
  </div>
</body>
</html>

