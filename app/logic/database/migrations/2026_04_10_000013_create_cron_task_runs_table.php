<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_task_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cron_task_id')->nullable()->constrained('cron_tasks')->nullOnDelete();
            $table->string('task_key', 120)->index();
            $table->enum('status', ['success', 'failed'])->index();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('output')->nullable();
            $table->timestamp('ran_at')->useCurrent()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_task_runs');
    }
};

