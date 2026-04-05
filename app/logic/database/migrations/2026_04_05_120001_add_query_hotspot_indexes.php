<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'posts_user_created_idx');
            $table->index(['user_id', 'published_at'], 'posts_user_published_idx');
        });

        Schema::table('post_platforms', function (Blueprint $table) {
            $table->index(['status', 'published_at'], 'post_platforms_status_published_idx');
            $table->index(['social_account_id', 'status'], 'post_platforms_social_status_idx');
            $table->index(['platform', 'published_at'], 'post_platforms_platform_published_idx');
        });

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->index(['user_id', 'platform', 'status'], 'social_accounts_user_platform_status_idx');
        });

        Schema::table('media_files', function (Blueprint $table) {
            $table->index(['user_id', 'deleted_at', 'created_at'], 'media_files_user_deleted_created_idx');
            $table->index(['user_id', 'deleted_at', 'type'], 'media_files_user_deleted_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_user_created_idx');
            $table->dropIndex('posts_user_published_idx');
        });

        Schema::table('post_platforms', function (Blueprint $table) {
            $table->dropIndex('post_platforms_status_published_idx');
            $table->dropIndex('post_platforms_social_status_idx');
            $table->dropIndex('post_platforms_platform_published_idx');
        });

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropIndex('social_accounts_user_platform_status_idx');
        });

        Schema::table('media_files', function (Blueprint $table) {
            $table->dropIndex('media_files_user_deleted_created_idx');
            $table->dropIndex('media_files_user_deleted_type_idx');
        });
    }
};

