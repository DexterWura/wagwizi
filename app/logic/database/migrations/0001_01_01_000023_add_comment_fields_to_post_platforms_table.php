<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_platforms', function (Blueprint $table) {
            $table->text('first_comment')->nullable()->after('platform_content');
            $table->unsignedInteger('comment_delay_minutes')->nullable()->after('first_comment');
        });
    }

    public function down(): void
    {
        Schema::table('post_platforms', function (Blueprint $table) {
            $table->dropColumn([
                'first_comment',
                'comment_delay_minutes',
            ]);
        });
    }
};
