<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_platforms', function (Blueprint $table) {
            $table->unsignedInteger('likes_count')->nullable()->after('published_at');
            $table->unsignedInteger('reposts_count')->nullable()->after('likes_count');
            $table->unsignedInteger('comments_count')->nullable()->after('reposts_count');
            $table->unsignedInteger('impressions_count')->nullable()->after('comments_count');
            $table->timestamp('metrics_synced_at')->nullable()->after('impressions_count');
        });
    }

    public function down(): void
    {
        Schema::table('post_platforms', function (Blueprint $table) {
            $table->dropColumn([
                'likes_count',
                'reposts_count',
                'comments_count',
                'impressions_count',
                'metrics_synced_at',
            ]);
        });
    }
};
