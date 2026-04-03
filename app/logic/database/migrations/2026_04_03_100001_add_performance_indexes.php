<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->index('published_at');
            $table->index(['user_id', 'status', 'published_at']);
            $table->index(['user_id', 'status', 'scheduled_at']);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['published_at']);
            $table->dropIndex(['user_id', 'status', 'published_at']);
            $table->dropIndex(['user_id', 'status', 'scheduled_at']);
            $table->dropIndex(['user_id', 'updated_at']);
        });

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
        });
    }
};
