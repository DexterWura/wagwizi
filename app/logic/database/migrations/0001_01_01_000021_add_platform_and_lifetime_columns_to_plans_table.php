<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->json('allowed_platforms')->nullable()->after('features');
            $table->boolean('is_lifetime')->default(false)->after('is_active');
            $table->unsignedInteger('lifetime_max_subscribers')->nullable()->after('is_lifetime');
            $table->unsignedInteger('lifetime_current_count')->default(0)->after('lifetime_max_subscribers');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'allowed_platforms',
                'is_lifetime',
                'lifetime_max_subscribers',
                'lifetime_current_count',
            ]);
        });
    }
};
