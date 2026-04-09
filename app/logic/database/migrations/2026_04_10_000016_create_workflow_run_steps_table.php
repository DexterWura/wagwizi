<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_run_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_run_id')->constrained('workflow_runs')->cascadeOnDelete();
            $table->string('node_id', 80);
            $table->string('node_type', 80);
            $table->string('status', 24)->default('queued'); // queued|running|completed|failed|skipped
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('attempt')->default(1);
            $table->json('input_payload')->nullable();
            $table->json('output_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('ai_tokens_used')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['workflow_run_id', 'position']);
            $table->index(['workflow_run_id', 'status']);
            $table->index(['node_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_run_steps');
    }
};

