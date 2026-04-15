<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $now = now();
        $templates = [
            [
                'key' => 'auth.password_reset',
                'name' => 'Password reset',
                'subject' => 'Reset your password — {{ $siteName }}',
                'body_html' => '<p>Hello {{ $userName }},</p><p>We received a request to reset your password.</p><p><a href="{{ $resetUrl }}">Reset password</a></p><p>If you did not request this, you can ignore this email.</p>',
                'body_text' => "Hello {{ \$userName }},\n\nWe received a request to reset your password.\n\nReset password: {{ \$resetUrl }}\n\nIf you did not request this, you can ignore this email.",
                'description' => 'Password reset flow email sent to end users.',
                'is_system' => true,
            ],
            [
                'key' => 'admin.user_registered',
                'name' => 'Admin alert — new user registered',
                'subject' => 'New user registered — {{ $siteName }}',
                'body_html' => '<p>A new user account was created.</p><p><strong>Name:</strong> {{ $newUserName }}<br><strong>Email:</strong> {{ $newUserEmail }}</p><p><a href="{{ $adminUsersUrl }}">Open users in admin</a></p>',
                'body_text' => "A new user account was created.\n\nName: {{ \$newUserName }}\nEmail: {{ \$newUserEmail }}\n\nOpen users in admin: {{ \$adminUsersUrl }}",
                'description' => 'Sent to active super admins when a new user signs up.',
                'is_system' => true,
            ],
            [
                'key' => 'admin.subscription_paid_new',
                'name' => 'Admin alert — new paid subscription',
                'subject' => 'New paid subscription — {{ $siteName }}',
                'body_html' => '<p>A new paid subscription was completed.</p><p><strong>User:</strong> {{ $subscriberName }} ({{ $subscriberEmail }})<br><strong>Plan:</strong> {{ $planName }}</p><p><a href="{{ $subscriptionsUrl }}">Open subscriptions in admin</a></p>',
                'body_text' => "A new paid subscription was completed.\n\nUser: {{ \$subscriberName }} ({{ \$subscriberEmail }})\nPlan: {{ \$planName }}\n\nOpen subscriptions in admin: {{ \$subscriptionsUrl }}",
                'description' => 'Sent to active super admins when a first paid subscription payment succeeds.',
                'is_system' => true,
            ],
            [
                'key' => 'admin.subscription_paid_renewal',
                'name' => 'Admin alert — paid subscription renewal',
                'subject' => 'Paid subscription renewed — {{ $siteName }}',
                'body_html' => '<p>A paid subscription renewed successfully.</p><p><strong>User:</strong> {{ $subscriberName }} ({{ $subscriberEmail }})<br><strong>Plan:</strong> {{ $planName }}</p><p><a href="{{ $subscriptionsUrl }}">Open subscriptions in admin</a></p>',
                'body_text' => "A paid subscription renewed successfully.\n\nUser: {{ \$subscriberName }} ({{ \$subscriberEmail }})\nPlan: {{ \$planName }}\n\nOpen subscriptions in admin: {{ \$subscriptionsUrl }}",
                'description' => 'Sent to active super admins when a recurring paid subscription payment succeeds.',
                'is_system' => true,
            ],
            [
                'key' => 'admin.smtp_test',
                'name' => 'Admin SMTP test email',
                'subject' => 'SMTP test email — {{ $siteName }}',
                'body_html' => '<p>Hello {{ $userName }},</p><p>This is a test email from {{ $siteName }}.</p><p>Sent at: {{ $sentAt }}</p>',
                'body_text' => "Hello {{ \$userName }},\n\nThis is a test email from {{ \$siteName }}.\nSent at: {{ \$sentAt }}",
                'description' => 'Used by the Admin notification settings SMTP test action.',
                'is_system' => true,
            ],
        ];

        foreach ($templates as $tpl) {
            $existing = DB::table('email_templates')->where('key', $tpl['key'])->first();
            if ($existing === null) {
                DB::table('email_templates')->insert(array_merge($tpl, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
                continue;
            }

            // Keep admin customizations; only patch old legacy password-reset boilerplate.
            if ($tpl['key'] === 'auth.password_reset' && is_string($existing->body_html ?? null)) {
                $legacyBody = '<p>Hello {{ $userName }},</p><p>We received a request to reset your password. Use the link below if you did not request this, you can ignore this email.</p>';
                if (trim((string) $existing->body_html) === $legacyBody) {
                    DB::table('email_templates')
                        ->where('id', $existing->id)
                        ->update([
                            'body_html' => $tpl['body_html'],
                            'body_text' => $tpl['body_text'],
                            'description' => $tpl['description'],
                            'updated_at' => $now,
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Keep templates on rollback; admins may have customized them.
    }
};

