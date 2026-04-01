<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->timestamp('trial_ends_at')->nullable()->after('current_period_end');

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropIndex(['user_id', 'status']);
            $table->dropColumn(['plan_id', 'trial_ends_at']);
        });
    }
};
