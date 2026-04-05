<?php

namespace App\Services\Media;

use App\Models\MediaFile;
use App\Models\User;
use App\Services\Cache\UserCacheVersionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MediaLibraryService
{
    private const MEDIA_COUNTS_CACHE_TTL = 60;

    /**
     * Single aggregate query — used for composer UX (device vs library) without paginator overhead.
     *
     * @return array{image: int, video: int}
     */
    public function typeCountsForUser(User $user): array
    {
        $cacheVersion = app(UserCacheVersionService::class)->current($user->id);
        $cacheKey = "media_type_counts:v1:{$cacheVersion}:{$user->id}";

        return Cache::remember($cacheKey, self::MEDIA_COUNTS_CACHE_TTL, function () use ($user): array {
            $row = MediaFile::query()
                ->where('user_id', $user->id)
                ->select([
                    DB::raw("SUM(CASE WHEN type = 'image' THEN 1 ELSE 0 END) as image_count"),
                    DB::raw("SUM(CASE WHEN type = 'video' THEN 1 ELSE 0 END) as video_count"),
                ])
                ->first();

            return [
                'image' => (int) ($row->image_count ?? 0),
                'video' => (int) ($row->video_count ?? 0),
            ];
        });
    }
}
