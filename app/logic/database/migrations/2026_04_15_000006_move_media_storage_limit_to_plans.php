<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'media_storage_limit_mb')) {
                // Recommended default: 2 GB per subscriber on each plan.
                $table->unsignedInteger('media_storage_limit_mb')->default(2048)->after('max_scheduled_posts_per_month');
            }
        });

        // Cleanup from previous user/global storage configuration (if present).
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'media_storage_limit_mb')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('media_storage_limit_mb');
            });
        }

        if (Schema::hasTable('site_settings')) {
            DB::table('site_settings')->where('key', 'media_default_storage_limit_mb')->delete();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'media_storage_limit_mb')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedInteger('media_storage_limit_mb')->nullable()->after('webhook_secret');
            });
        }

        if (Schema::hasTable('site_settings')) {
            DB::table('site_settings')->updateOrInsert(
                ['key' => 'media_default_storage_limit_mb'],
                ['value' => '2048', 'created_at' => now(), 'updated_at' => now()]
            );
        }

        Schema::table('plans', function (Blueprint $table) {
            if (Schema::hasColumn('plans', 'media_storage_limit_mb')) {
                $table->dropColumn('media_storage_limit_mb');
            }
        });
    }
};

