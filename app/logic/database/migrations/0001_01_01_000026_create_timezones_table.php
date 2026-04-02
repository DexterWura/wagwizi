<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timezones', function (Blueprint $table) {
            $table->id();
            $table->string('identifier', 128)->unique();
            $table->string('label_short', 64);
            $table->string('label_long', 255);
            $table->timestamps();
        });

        $identifiers = \DateTimeZone::listIdentifiers(\DateTimeZone::ALL);
        $now = now();
        $rows = [];

        foreach ($identifiers as $id) {
            try {
                $short = Carbon::now($id)->timezoneAbbreviatedName;
                if ($short === '') {
                    $short = $id;
                }
            } catch (\Throwable) {
                $short = $id;
            }
            $long = str_replace('/', ' / ', $id);
            $rows[] = [
                'identifier'   => $id,
                'label_short'  => $short,
                'label_long'   => $long,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }

        foreach (array_chunk($rows, 150) as $chunk) {
            DB::table('timezones')->insert($chunk);
        }

        if (Schema::hasTable('site_settings')) {
            $exists = DB::table('site_settings')->where('key', 'default_display_timezone')->exists();
            if ($exists) {
                DB::table('site_settings')
                    ->where('key', 'default_display_timezone')
                    ->update(['value' => 'UTC', 'updated_at' => $now]);
            } else {
                DB::table('site_settings')->insert([
                    'key'         => 'default_display_timezone',
                    'value'       => 'UTC',
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('site_settings')) {
            DB::table('site_settings')->where('key', 'default_display_timezone')->delete();
        }

        Schema::dropIfExists('timezones');
    }
};
