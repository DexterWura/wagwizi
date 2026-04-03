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
            $table->unsignedBigInteger('platform_ai_tokens_per_period')->default(0)->after('free_trial_days');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('platform_ai_tokens_remaining')->default(0)->after('trial_ends_at');
        });

        if (Schema::hasTable('plans') && Schema::hasTable('subscriptions')) {
            DB::statement('
                UPDATE subscriptions AS s
                INNER JOIN plans AS p ON s.plan_id = p.id
                SET s.platform_ai_tokens_remaining = p.platform_ai_tokens_per_period
            ');
        }
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('platform_ai_tokens_remaining');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('platform_ai_tokens_per_period');
        });
    }
};
