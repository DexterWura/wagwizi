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

        // Recommended default quota: 2 GB per user.
        DB::table('site_settings')->updateOrInsert(
            ['key' => 'media_default_storage_limit_mb'],
            ['value' => '2048', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        DB::table('site_settings')->where('key', 'media_default_storage_limit_mb')->delete();
    }
};

