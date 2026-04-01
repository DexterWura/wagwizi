<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_platforms', function (Blueprint $table) {
            $table->enum('comment_status', ['pending', 'queued', 'published', 'failed'])
                ->nullable()
                ->after('comment_delay_minutes')
                ->index();
            $table->text('comment_error_message')->nullable()->after('comment_status');
            $table->timestamp('comment_published_at')->nullable()->after('comment_error_message');
        });
    }

    public function down(): void
    {
        Schema::table('post_platforms', function (Blueprint $table) {
            $table->dropColumn([
                'comment_status',
                'comment_error_message',
                'comment_published_at',
            ]);
        });
    }
};

