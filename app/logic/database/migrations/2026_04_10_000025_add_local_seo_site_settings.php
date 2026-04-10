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
            'seo_local_business_name' => '',
            'seo_local_phone' => '',
            'seo_local_email' => '',
            'seo_local_address' => '',
            'seo_local_city' => '',
            'seo_local_region' => '',
            'seo_local_postal_code' => '',
            'seo_local_country_code' => '',
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
                'seo_local_business_name',
                'seo_local_phone',
                'seo_local_email',
                'seo_local_address',
                'seo_local_city',
                'seo_local_region',
                'seo_local_postal_code',
                'seo_local_country_code',
            ])
            ->delete();
    }
};

