<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_settings')) {
            DB::table('site_settings')->updateOrInsert(
                ['key' => 'signup_email_otp_enabled'],
                ['value' => '0', 'created_at' => now(), 'updated_at' => now()]
            );
        }

        if (! Schema::hasTable('email_templates')) {
            return;
        }

        DB::table('email_templates')->updateOrInsert(
            ['key' => 'auth.signup_otp'],
            [
                'name' => 'Signup email OTP',
                'subject' => 'Your signup code — {{ $siteName }}',
                'body_html' => '<p>Hello {{ $userName }},</p><p>Your verification code is <strong style="font-size:20px;letter-spacing:2px;">{{ $otpCode }}</strong>.</p><p>This code expires in {{ $otpExpiresMinutes }} minutes.</p><p>If you did not start signup, you can ignore this message.</p>',
                'body_text' => "Hello {{ \$userName }},\n\nYour verification code is: {{ \$otpCode }}\n\nThis code expires in {{ \$otpExpiresMinutes }} minutes.\nIf you did not start signup, you can ignore this message.",
                'description' => 'Sent during email/password signup when signup OTP is enabled.',
                'is_system' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        // Keep settings/template on rollback to avoid deleting admin customizations.
    }
};

