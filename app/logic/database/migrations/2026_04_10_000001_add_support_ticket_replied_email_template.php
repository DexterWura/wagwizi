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

        if (DB::table('email_templates')->where('key', 'support.ticket_replied')->exists()) {
            return;
        }

        $now = now();
        DB::table('email_templates')->insert([
            'key'         => 'support.ticket_replied',
            'name'        => 'Support ticket — staff reply',
            'subject'     => 'New reply on your ticket #{{ $ticketId }} — {{ $siteName }}',
            'body_html'   => '<p>Hello {{ $userName }},</p>'
                . '<p>{{ $responderName }} replied to your support ticket <strong>#{{ $ticketId }}</strong>: {{ $ticketSubject }}</p>'
                . '<p><a href="{{ $ticketUrl }}">View ticket</a></p>'
                . '<p>— {{ $siteName }}</p>',
            'body_text'   => "Hello {{ \$userName }},\n\n"
                . "{{ \$responderName }} replied to your support ticket #{{ \$ticketId }}: {{ \$ticketSubject }}\n\n"
                . "Open: {{ \$ticketUrl }}\n\n"
                . "— {{ \$siteName }}",
            'description' => 'Sent when a team member replies to a customer support ticket from the admin area.',
            'is_system'   => true,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        DB::table('email_templates')->where('key', 'support.ticket_replied')->delete();
    }
};
