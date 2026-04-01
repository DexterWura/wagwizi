<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->unsignedInteger('monthly_price_cents')->nullable();
            $table->unsignedInteger('yearly_price_cents')->nullable();
            $table->unsignedSmallInteger('max_social_profiles')->nullable();
            $table->unsignedInteger('max_scheduled_posts_per_month')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
