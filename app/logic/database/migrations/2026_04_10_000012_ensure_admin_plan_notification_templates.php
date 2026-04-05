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
                'key' => 'subscription.admin_changed',
                'name' => 'Subscription changed by admin',
                'subject' => 'Your plan was changed by admin — {{ $siteName }}',
                'body_html' => '<p>Hello {{ $userName }},</p><p>Your plan was changed by an administrator to <strong>{{ $planName }}</strong>.</p><p>Previous plan: {{ $previousPlanName }}</p>',
                'body_text' => "Hello {{ \$userName }},\n\nYour plan was changed by an administrator to {{ \$planName }}.\nPrevious plan: {{ \$previousPlanName }}",
                'description' => 'Sent when an admin manually changes a user plan.',
                'is_system' => true,
            ],
            [
                'key' => 'subscription.gifted',
                'name' => 'Plan gifted by admin',
                'subject' => 'You were gifted a plan — {{ $siteName }}',
                'body_html' => '<p>Hello {{ $userName }},</p><p>Good news! An administrator gifted you access to <strong>{{ $planName }}</strong>.</p><p>Previous plan: {{ $previousPlanName }}</p>',
                'body_text' => "Hello {{ \$userName }},\n\nGood news! An administrator gifted you access to {{ \$planName }}.\nPrevious plan: {{ \$previousPlanName }}",
                'description' => 'Sent when admin gifts a plan to a user.',
                'is_system' => true,
            ],
        ];

        foreach ($templates as $tpl) {
            if (DB::table('email_templates')->where('key', $tpl['key'])->exists()) {
                continue;
            }

            DB::table('email_templates')->insert(array_merge($tpl, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        // Keep templates on rollback; they may be customized in production.
    }
};

