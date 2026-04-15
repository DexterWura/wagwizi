<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (!Schema::hasColumn('plans', 'includes_webhooks')) {
                $table->boolean('includes_webhooks')->default(false)->after('includes_workflows');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (Schema::hasColumn('plans', 'includes_webhooks')) {
                $table->dropColumn('includes_webhooks');
            }
        });
    }
};

