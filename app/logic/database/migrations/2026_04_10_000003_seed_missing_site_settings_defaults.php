<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
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
            'app_name' => config('app.name'),
            'app_tagline' => '',
            'hero_eyebrow' => 'Social OS',
            'hero_heading' => 'Your agentic social media scheduling tool',
            'hero_subheading' => 'One workspace to compose, preview every network, schedule with drag-and-drop, and ship with confidence.',
            'registration_open' => '1',
            'show_floating_help' => '1',
            'social_login_google' => '1',
            'social_login_linkedin' => '1',
            'enabled_platforms' => json_encode([]),
            'landing_features_deep' => json_encode([]),
            'paused_platforms' => json_encode([]),
            'publish_retry_policy' => json_encode([
                'max_tries' => 3,
                'backoff_seconds' => [10, 30, 90],
                'text_only_fallback' => true,
            ]),
        ];

        foreach ($defaults as $key => $value) {
            $exists = DB::table('site_settings')->where('key', $key)->exists();
            if (!$exists) {
                DB::table('site_settings')->insert([
                    'key' => $key,
                    'value' => is_string($value) ? $value : (string) $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            Cache::forget("site_setting.{$key}");
        }
    }

    public function down(): void
    {
        // Intentionally no-op to avoid deleting user-customized settings values.
    }
};

