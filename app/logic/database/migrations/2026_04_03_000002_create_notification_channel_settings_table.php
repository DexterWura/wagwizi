<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_channel_settings', function (Blueprint $table) {
            $table->id();
            $table->string('driver', 32)->default('log');
            $table->string('from_name')->nullable();
            $table->string('from_address')->nullable();
            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->nullable();
            $table->string('smtp_encryption', 16)->nullable();
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();
            $table->unsignedSmallInteger('smtp_timeout')->nullable();
            $table->string('reply_to')->nullable();
            $table->string('sms_provider', 32)->default('none');
            $table->text('sms_credentials')->nullable();
            $table->longText('master_template_html')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_channel_settings');
    }
};
