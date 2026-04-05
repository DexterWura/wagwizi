<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;

final class UserCacheVersionService
{
    private const TTL_SECONDS = 2_592_000; // 30 days

    public function current(int $userId): int
    {
        $value = Cache::get($this->key($userId), 1);
        $version = is_numeric($value) ? (int) $value : 1;

        return max(1, $version);
    }

    public function bump(int $userId): int
    {
        $next = $this->current($userId) + 1;
        Cache::put($this->key($userId), $next, self::TTL_SECONDS);

        return $next;
    }

    private function key(int $userId): string
    {
        return "user_cache_version:v1:{$userId}";
    }
}

