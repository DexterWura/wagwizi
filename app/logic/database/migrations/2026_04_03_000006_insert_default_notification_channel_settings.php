<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $master = <<<'HTML'
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>{{ $siteName }}</title></head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5;">
<div style="max-width: 600px; margin: 0 auto; padding: 24px;">
{{ $bodyHtml }}
<hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">
<p style="font-size: 12px; color: #666;">{{ $siteName }}</p>
</div>
</body>
</html>
HTML;

        DB::table('notification_channel_settings')->insert([
            'driver'               => 'log',
            'from_name'            => null,
            'from_address'         => null,
            'smtp_host'            => null,
            'smtp_port'            => null,
            'smtp_encryption'      => null,
            'smtp_username'        => null,
            'smtp_password'        => null,
            'smtp_timeout'         => null,
            'reply_to'             => null,
            'sms_provider'         => 'none',
            'sms_credentials'      => null,
            'master_template_html' => $master,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('notification_channel_settings')->truncate();
    }
};
