<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        $defaultTools = [
            'youtube_video_download',
            'linkedin_video_download',
            'twitter_video_download',
            'facebook_video_download',
            'instagram_reels_download',
            'tiktok_video_download',
            'vimeo_video_download',
            'pinterest_media_download',
            'reddit_media_download',
            'bulk_media_import',
            'canva_export_import',
            'ai_caption_generator',
        ];

        DB::table('site_settings')->updateOrInsert(
            ['key' => 'enabled_download_tools'],
            ['value' => json_encode($defaultTools, JSON_UNESCAPED_SLASHES), 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        DB::table('site_settings')->where('key', 'enabled_download_tools')->delete();
    }
};

