<?php

namespace App\Services\Post;

use App\Models\Post;
use App\Services\Platform\Platform;

/**
 * Cheap pre-publish checks using platform_media_constraints (file size; dimensions optional later).
 */
final class MediaPublishPreflightService
{
    public function validatePostMediaForPlatform(Post $post, Platform $platform): ?string
    {
        $constraints = config('platform_media_constraints.' . $platform->value);
        if (! is_array($constraints) || $constraints === []) {
            return null;
        }

        $post->loadMissing('mediaFiles');

        foreach ($post->mediaFiles as $file) {
            $type = $file->type === 'video' ? 'video' : 'image';
            $rules = $constraints[$type] ?? null;
            if (! is_array($rules)) {
                continue;
            }

            $maxMb = $rules['max_file_mb'] ?? null;
            if ($maxMb !== null && $file->size_bytes !== null) {
                $limitBytes = (int) $maxMb * 1024 * 1024;
                if ((int) $file->size_bytes > $limitBytes) {
                    $name = $file->original_name ?: basename((string) $file->path);

                    return "{$platform->label()}: \"{$name}\" exceeds the {$type} size limit ({$maxMb} MB).";
                }
            }
        }

        return null;
    }
}
