<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Password reset</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;color:#0f172a;font-family:Arial,sans-serif;">
  <div style="max-width:620px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;">
    <h1 style="margin:0 0 12px;font-size:22px;line-height:1.2;">Reset your password</h1>
    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
      Hello {{ $user->name }}, we received a request to reset your {{ config('app.name') }} password.
    </p>
    <p style="margin:0 0 20px;">
      <a href="{{ $resetUrl }}" style="display:inline-block;background:#f97316;color:#1a0a00;text-decoration:none;font-weight:700;padding:12px 18px;border-radius:8px;">
        Reset password
      </a>
    </p>
    <p style="margin:0 0 10px;font-size:13px;line-height:1.6;color:#475569;">
      This link expires in 60 minutes. If you did not request a password reset, you can safely ignore this email.
    </p>
    <p style="margin:0;font-size:12px;line-height:1.6;color:#64748b;word-break:break-word;">
      If the button does not work, copy and paste this URL into your browser:<br />
      <a href="{{ $resetUrl }}" style="color:#2563eb;">{{ $resetUrl }}</a>
    </p>
  </div>
</body>
</html>

