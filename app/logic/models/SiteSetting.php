<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    protected $fillable = ['key', 'value'];

    private const CACHE_TTL = 3600;

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("site_setting.{$key}", self::CACHE_TTL, function () use ($key, $default) {
            $row = static::where('key', $key)->first();
            return $row ? $row->value : $default;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("site_setting.{$key}");
    }

    public static function getJson(string $key, array $default = []): array
    {
        $raw = static::get($key);
        if ($raw === null) return $default;
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : $default;
    }

    public static function setJson(string $key, array $value): void
    {
        static::set($key, json_encode($value));
    }
}
