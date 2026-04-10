<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('users')
            || ! Schema::hasTable('workspaces')
            || ! Schema::hasTable('workspace_memberships')
        ) {
            return;
        }

        $now = now();
        $users = DB::table('users')->select(['id', 'name', 'workspace_name'])->get();
        foreach ($users as $user) {
            $workspaceId = DB::table('workspaces')
                ->where('owner_user_id', $user->id)
                ->value('id');

            if ($workspaceId === null) {
                $name = trim((string) ($user->workspace_name ?? ''));
                if ($name === '') {
                    $name = trim((string) ($user->name ?? ''));
                }
                if ($name === '') {
                    $name = 'Personal workspace';
                }

                $workspaceId = DB::table('workspaces')->insertGetId([
                    'owner_user_id' => $user->id,
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $exists = DB::table('workspace_memberships')
                ->where('workspace_id', $workspaceId)
                ->where('user_id', $user->id)
                ->exists();

            if (! $exists) {
                DB::table('workspace_memberships')->insert([
                    'workspace_id' => $workspaceId,
                    'user_id' => $user->id,
                    'role' => 'admin',
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Keep backfilled data intact on rollback.
    }
};

