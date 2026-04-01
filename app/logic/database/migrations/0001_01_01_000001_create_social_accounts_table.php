<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 50)->index();
            $table->string('platform_user_id');
            $table->string('username')->nullable();
            $table->string('display_name')->nullable();
            $table->string('avatar_url')->nullable();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->enum('status', ['active', 'expired', 'revoked'])->default('active')->index();
            $table->timestamps();

            $table->unique(['user_id', 'platform', 'platform_user_id'], 'social_accounts_composite_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
