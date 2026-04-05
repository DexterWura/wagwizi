<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('notification_channel_settings');

        Schema::create('notification_channel_settings', function (Blueprint $table) {
            $table->id();
            $table->string('email_send_method', 32)->default('smtp');
            $table->string('smtp_host');
            $table->unsignedSmallInteger('smtp_port')->default(465);
            $table->string('smtp_encryption', 16)->default('ssl');
            $table->string('smtp_username');
            $table->text('smtp_password');
            $table->timestamps();
        });

        DB::table('notification_channel_settings')->insert([
            'email_send_method' => 'smtp',
            'smtp_host' => 'mail.dextersoft.com',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'smtp_username' => 'info@dextersoft.com',
            'smtp_password' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_channel_settings');

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
};

