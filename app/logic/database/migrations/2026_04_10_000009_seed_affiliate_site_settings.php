<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        $now = now();

        DB::table('site_settings')->updateOrInsert(
            ['key' => 'affiliate_program_enabled'],
            ['value' => '0', 'created_at' => $now, 'updated_at' => $now]
        );

        DB::table('site_settings')->updateOrInsert(
            ['key' => 'affiliate_first_subscription_percent'],
            ['value' => '10.00', 'created_at' => $now, 'updated_at' => $now]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        DB::table('site_settings')->whereIn('key', [
            'affiliate_program_enabled',
            'affiliate_first_subscription_percent',
        ])->delete();
    }
};

