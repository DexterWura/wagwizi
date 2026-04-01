<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_platforms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform', 50)->index();
            $table->text('platform_content')->nullable();
            $table->string('platform_post_id')->nullable();
            $table->enum('status', ['pending', 'publishing', 'published', 'failed'])->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['post_id', 'status']);
            $table->unique(['post_id', 'platform', 'social_account_id'], 'post_platform_account_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_platforms');
    }
};
