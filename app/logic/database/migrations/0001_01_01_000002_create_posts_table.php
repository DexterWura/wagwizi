<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->json('media_paths')->nullable();
            $table->json('platforms');
            $table->enum('status', ['draft', 'scheduled', 'publishing', 'published', 'failed'])->default('draft')->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
