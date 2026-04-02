<?php

namespace App\Services\Media;

use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MediaLibraryService
{
    /**
     * Single aggregate query — used for composer UX (device vs library) without paginator overhead.
     *
     * @return array{image: int, video: int}
     */
    public function typeCountsForUser(User $user): array
    {
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
    }
}
