<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->foreignId('to_plan_id')->constrained('plans')->cascadeOnDelete();
            $table->enum('change_type', ['upgrade', 'downgrade', 'cancel', 'reactivate'])->index();
            $table->string('gateway', 50)->nullable();
            $table->string('gateway_event_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_changes');
    }
};
