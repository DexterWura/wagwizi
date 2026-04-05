<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('site_settings')) {
            return;
        }

        $now = now();
        $defaults = [
            'seo_meta_title' => '',
            'seo_meta_description' => '',
            'seo_social_description' => '',
            'seo_keywords' => '',
            'seo_twitter_site' => '',
            'seo_image_path' => '',
            'seo_favicon_path' => '',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('site_settings')->updateOrInsert(
                ['key' => $key],
                [
                    'value' => $value,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('site_settings')) {
            return;
        }

        DB::table('site_settings')
            ->whereIn('key', [
                'seo_meta_title',
                'seo_meta_description',
                'seo_social_description',
                'seo_keywords',
                'seo_twitter_site',
                'seo_image_path',
                'seo_favicon_path',
            ])
            ->delete();
    }
};

