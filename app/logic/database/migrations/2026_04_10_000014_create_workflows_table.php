<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('status', 24)->default('draft'); // draft|active|paused|archived
            $table->string('trigger_type', 32)->default('manual'); // manual|schedule|event
            $table->json('trigger_config')->nullable();
            $table->json('graph')->nullable(); // nodes + edges JSON for builder and runner
            $table->unsignedInteger('graph_version')->default(1);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'trigger_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};

