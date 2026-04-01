<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true)->index();
            $table->unsignedInteger('interval_minutes')->default(1);
            $table->timestamp('last_ran_at')->nullable();
            $table->unsignedInteger('last_duration_ms')->nullable();
            $table->enum('last_status', ['success', 'failed', 'running'])->nullable();
            $table->text('last_output')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_tasks');
    }
};
