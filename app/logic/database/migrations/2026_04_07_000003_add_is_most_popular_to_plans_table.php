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
            $table->boolean('is_most_popular')->default(false)->after('is_active');
        });

        // Match previous landing-page behavior (slug "growth" was hardcoded as featured).
        DB::table('plans')->where('slug', 'growth')->update(['is_most_popular' => true]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('is_most_popular');
        });
    }
};
