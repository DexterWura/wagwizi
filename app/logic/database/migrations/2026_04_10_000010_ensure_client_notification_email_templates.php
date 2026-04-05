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
                'key' => 'subscription.updated',
                'name' => 'Subscription updated',
                'subject' => 'Your subscription at {{ $siteName }}',
                'body_html' => '<p>Hello {{ $userName }},</p><p>Your subscription has been updated to <strong>{{ $planName }}</strong>.</p>',
                'body_text' => "Hello {{ \$userName }},\n\nYour subscription has been updated to {{ \$planName }}.",
                'description' => 'Sent when subscription status or plan changes.',
                'is_system' => true,
            ],
            [
                'key' => 'subscription.downgrade',
                'name' => 'Plan downgrade',
                'subject' => 'Your plan has changed — {{ $siteName }}',
                'body_html' => '<p>Hello {{ $userName }},</p><p>Your plan was changed to <strong>{{ $planName }}</strong>.</p>',
                'body_text' => "Hello {{ \$userName }},\n\nYour plan was changed to {{ \$planName }}.",
                'description' => 'Sent after a downgrade.',
                'is_system' => true,
            ],
            [
                'key' => 'subscription.reminder',
                'name' => 'Subscription renewal reminder',
                'subject' => 'Your {{ $siteName }} plan renews soon',
                'body_html' => '<p>Hello {{ $userName }},</p><p>Your <strong>{{ $planName }}</strong> subscription renews in {{ $daysLeft }} day(s).</p><p>Renewal date: {{ $renewsAt }}</p>',
                'body_text' => "Hello {{ \$userName }},\n\nYour {{ \$planName }} subscription renews in {{ \$daysLeft }} day(s).\nRenewal date: {{ \$renewsAt }}.",
                'description' => 'Renewal reminder for active paid subscriptions.',
                'is_system' => true,
            ],
            [
                'key' => 'subscription.trial_ending',
                'name' => 'Trial ending soon',
                'subject' => 'Your {{ $siteName }} trial ends soon',
                'body_html' => '<p>Hello {{ $userName }},</p><p>Your trial for <strong>{{ $planName }}</strong> ends in {{ $daysLeft }} day(s).</p><p>Trial end date: {{ $trialEndsAt }}</p>',
                'body_text' => "Hello {{ \$userName }},\n\nYour trial for {{ \$planName }} ends in {{ \$daysLeft }} day(s).\nTrial end date: {{ \$trialEndsAt }}.",
                'description' => 'Trial end reminder.',
                'is_system' => true,
            ],
            [
                'key' => 'support.ticket_replied',
                'name' => 'Support ticket — staff reply',
                'subject' => 'New reply on your ticket #{{ $ticketId }} — {{ $siteName }}',
                'body_html' => '<p>Hello {{ $userName }},</p><p>{{ $responderName }} replied to your support ticket <strong>#{{ $ticketId }}</strong>: {{ $ticketSubject }}</p><p><a href="{{ $ticketUrl }}">View ticket</a></p>',
                'body_text' => "Hello {{ \$userName }},\n\n{{ \$responderName }} replied to your support ticket #{{ \$ticketId }}: {{ \$ticketSubject }}\n\nOpen: {{ \$ticketUrl }}",
                'description' => 'Sent when staff replies to a customer support ticket.',
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

