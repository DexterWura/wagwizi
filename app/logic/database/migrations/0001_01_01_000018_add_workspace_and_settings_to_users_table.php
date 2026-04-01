<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('workspace_name', 255)->nullable()->after('bio');
            $table->string('workspace_slug', 100)->nullable()->after('workspace_name');
            $table->string('default_posting_time', 5)->nullable()->after('workspace_slug');
            $table->json('notification_preferences')->nullable()->after('default_posting_time');
            $table->string('ai_source', 20)->nullable()->after('notification_preferences');
            $table->string('ai_provider', 20)->nullable()->after('ai_source');
            $table->string('ai_base_url', 500)->nullable()->after('ai_provider');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'workspace_name',
                'workspace_slug',
                'default_posting_time',
                'notification_preferences',
                'ai_source',
                'ai_provider',
                'ai_base_url',
            ]);
        });
    }
};
