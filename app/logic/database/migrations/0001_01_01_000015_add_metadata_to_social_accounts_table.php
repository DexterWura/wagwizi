<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('scopes');
        });

        DB::statement("ALTER TABLE social_accounts MODIFY COLUMN status ENUM('active','expired','revoked','disconnected') DEFAULT 'active'");
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });

        DB::statement("ALTER TABLE social_accounts MODIFY COLUMN status ENUM('active','expired','revoked') DEFAULT 'active'");
    }
};
