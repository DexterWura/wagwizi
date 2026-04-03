<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('subject');
            $table->longText('body_html');
            $table->text('body_text')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->index('key');
        });

        $now = now();
        $defaults = [
            [
                'key'         => 'subscription.updated',
                'name'        => 'Subscription updated',
                'subject'     => 'Your subscription at {{ $siteName }}',
                'body_html'   => '<p>Hello {{ $userName }},</p><p>Your subscription has been updated.</p>',
                'body_text'   => "Hello {{ \$userName }},\n\nYour subscription has been updated.",
                'description' => 'Sent when subscription status or plan changes.',
                'is_system'   => true,
            ],
            [
                'key'         => 'auth.password_reset',
                'name'        => 'Password reset',
                'subject'     => 'Reset your password — {{ $siteName }}',
                'body_html'   => '<p>Hello {{ $userName }},</p><p>We received a request to reset your password. Use the link below if you did not request this, you can ignore this email.</p>',
                'body_text'   => null,
                'description' => 'Password reset flow.',
                'is_system'   => true,
            ],
            [
                'key'         => 'subscription.reminder',
                'name'        => 'Subscription renewal reminder',
                'subject'     => 'Renewal reminder — {{ $siteName }}',
                'body_html'   => '<p>Hello {{ $userName }},</p><p>This is a reminder about your subscription.</p>',
                'body_text'   => null,
                'description' => 'Renewal or billing reminder.',
                'is_system'   => true,
            ],
            [
                'key'         => 'subscription.trial_ending',
                'name'        => 'Trial ending soon',
                'subject'     => 'Your trial ends soon — {{ $siteName }}',
                'body_html'   => '<p>Hello {{ $userName }},</p><p>Your trial period is ending soon.</p>',
                'body_text'   => null,
                'description' => 'Trial end reminder.',
                'is_system'   => true,
            ],
            [
                'key'         => 'subscription.downgrade',
                'name'        => 'Plan downgrade',
                'subject'     => 'Your plan has changed — {{ $siteName }}',
                'body_html'   => '<p>Hello {{ $userName }},</p><p>Your plan has been downgraded.</p>',
                'body_text'   => null,
                'description' => 'Sent after a downgrade.',
                'is_system'   => true,
            ],
        ];

        foreach ($defaults as $row) {
            DB::table('email_templates')->insert(array_merge($row, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
