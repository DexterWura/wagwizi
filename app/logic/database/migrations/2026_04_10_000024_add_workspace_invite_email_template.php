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

        $key = 'workspace.invite';
        if (DB::table('email_templates')->where('key', $key)->exists()) {
            return;
        }

        DB::table('email_templates')->insert([
            'key' => $key,
            'name' => 'Workspace invite',
            'subject' => 'You are invited to join {{ $workspaceName }}',
            'body_html' => '<p>Hello {{ $userName }},</p><p>{{ $inviterName }} invited you to join <strong>{{ $workspaceName }}</strong> as {{ $inviteRole }}.</p><p><a href="{{ $inviteUrl }}">Accept invite</a></p>',
            'body_text' => "Hello {{ \$userName }},\n\n{{ \$inviterName }} invited you to join {{ \$workspaceName }} as {{ \$inviteRole }}.\n\nAccept invite: {{ \$inviteUrl }}",
            'description' => 'Sent when a workspace admin invites a team member.',
            'is_system' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Keep template on rollback.
    }
};

