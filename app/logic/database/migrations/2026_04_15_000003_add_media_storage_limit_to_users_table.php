<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'media_storage_limit_mb')) {
                $table->unsignedInteger('media_storage_limit_mb')->nullable()->after('webhook_secret');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'media_storage_limit_mb')) {
                $table->dropColumn('media_storage_limit_mb');
            }
        });
    }
};

