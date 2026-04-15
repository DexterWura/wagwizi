<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'webhook_key_id')) {
                $table->string('webhook_key_id', 64)->nullable()->unique()->after('ai_api_key');
            }
            if (!Schema::hasColumn('users', 'webhook_secret')) {
                $table->text('webhook_secret')->nullable()->after('webhook_key_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'webhook_secret')) {
                $table->dropColumn('webhook_secret');
            }
            if (Schema::hasColumn('users', 'webhook_key_id')) {
                $table->dropUnique('users_webhook_key_id_unique');
                $table->dropColumn('webhook_key_id');
            }
        });
    }
};

