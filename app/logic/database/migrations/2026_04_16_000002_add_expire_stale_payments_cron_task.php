<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cron_tasks')) {
            return;
        }

        $exists = DB::table('cron_tasks')->where('key', 'expire_stale_payments')->exists();
        if ($exists) {
            return;
        }

        DB::table('cron_tasks')->insert([
            'key' => 'expire_stale_payments',
            'label' => 'Expire stale pending payments',
            'description' => 'Marks old pending checkout attempts as failed so users can retry cleanly.',
            'enabled' => true,
            'interval_minutes' => 1440,
            'last_ran_at' => null,
            'last_duration_ms' => null,
            'last_status' => null,
            'last_output' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('cron_tasks')) {
            return;
        }

        DB::table('cron_tasks')->where('key', 'expire_stale_payments')->delete();
    }
};

