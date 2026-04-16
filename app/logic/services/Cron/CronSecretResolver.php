<?php

declare(strict_types=1);

namespace App\Services\Cron;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Cron HTTP auth secret: read from the database first (encrypted in site_settings).
 * If no row exists, falls back to CRON_SECRET from the environment.
 */
final class CronSecretResolver
{
    private const SETTING_KEY = 'cron_secret_storage';

    public function get(): string
    {
        $this->ensureStoredSecret();

        $fromDb = $this->getFromDatabase();
        if ($fromDb !== '') {
            return $fromDb;
        }

        return trim((string) config('app.cron_secret', ''));
    }

    /**
     * Persist CRON_SECRET from environment into database when no stored value exists yet.
     */
    private function ensureStoredSecret(): void
    {
        if ($this->isStoredInDatabase()) {
            return;
        }

        $fromEnv = trim((string) config('app.cron_secret', ''));
        if ($fromEnv === '') {
            return;
        }

        try {
            $this->store($fromEnv);
        } catch (\Throwable) {
            // Keep runtime behavior intact if DB is unavailable.
        }
    }

    private function getFromDatabase(): string
    {
        $enc = SiteSetting::get(self::SETTING_KEY);
        if (! is_string($enc) || $enc === '') {
            return '';
        }

        try {
            return decrypt($enc);
        } catch (\Throwable) {
            return '';
        }
    }

    public function isStoredInDatabase(): bool
    {
        $enc = SiteSetting::get(self::SETTING_KEY);

        return is_string($enc) && $enc !== '';
    }

    public function store(string $plain): void
    {
        SiteSetting::set(self::SETTING_KEY, encrypt($plain));
    }

    public function clearStored(): void
    {
        SiteSetting::query()->where('key', self::SETTING_KEY)->delete();
        Cache::forget('site_setting.' . self::SETTING_KEY);
    }
}
