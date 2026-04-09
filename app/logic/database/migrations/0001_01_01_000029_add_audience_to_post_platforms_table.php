<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_platforms', function (Blueprint $table) {
            if (!Schema::hasColumn('post_platforms', 'audience')) {
                $table->string('audience', 32)->nullable()->after('platform_content');
            }
        });
    }

    public function down(): void
    {
        Schema::table('post_platforms', function (Blueprint $table) {
            if (Schema::hasColumn('post_platforms', 'audience')) {
                $table->dropColumn('audience');
            }
        });
    }
};
