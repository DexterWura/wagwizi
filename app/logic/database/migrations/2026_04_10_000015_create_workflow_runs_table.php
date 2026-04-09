<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('trigger_type', 32)->default('manual'); // manual|schedule|event
            $table->string('status', 24)->default('queued'); // queued|running|completed|failed|cancelled
            $table->json('context')->nullable();
            $table->unsignedInteger('steps_total')->default(0);
            $table->unsignedInteger('steps_succeeded')->default(0);
            $table->unsignedInteger('steps_failed')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['workflow_id', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_runs');
    }
};

