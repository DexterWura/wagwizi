<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('linkedin_id')->nullable()->unique()->after('google_id');
            $table->string('password')->nullable()->change();
            $table->boolean('profile_completed')->default(true)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'linkedin_id', 'profile_completed']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
