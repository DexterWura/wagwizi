<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->boolean('includes_workspaces')->default(false)->after('includes_workflows');
            $table->unsignedInteger('max_workspace_members')->nullable()->after('includes_workspaces');
            $table->unsignedInteger('max_accounts_per_platform')->nullable()->after('max_workspace_members');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn([
                'includes_workspaces',
                'max_workspace_members',
                'max_accounts_per_platform',
            ]);
        });
    }
};

