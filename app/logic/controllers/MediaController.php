<?php

namespace App\Controllers;

use App\Models\MediaFile;
use App\Services\Cache\UserCacheVersionService;
use App\Services\Media\MediaStorageQuotaService;
use App\Utils\FileUploadUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MediaController extends Controller
{
    public function __construct(
        private readonly MediaStorageQuotaService $mediaQuota,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Auth::user()->mediaFiles()->orderByDesc('created_at');

        $type = $request->query('type');
        if ($type === 'image') {
            $query->images();
        } elseif ($type === 'video') {
            $query->videos();
        }

        $media = $query->paginate(24);

        return response()->json([
            'success' => true,
            'media'   => $media,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:jpeg,jpg,png,gif,webp,mp4,mov,avi|max:51200',
        ]);

        $user = Auth::user();
        $file = $request->file('file');
        // Read metadata before move(): move() deletes the temp file; getSize() would stat a missing path.
        $mime          = $file->getMimeType();
        $sizeBytes     = $file->getSize();
        $originalName  = $file->getClientOriginalName();
        $type          = str_starts_with((string) $mime, 'video/') ? 'video' : 'image';
        $subDir        = $type === 'video' ? 'videos' : 'images';

        if (! $this->mediaQuota->userCanStoreAdditionalBytes($user, (int) $sizeBytes)) {
            $summary = $this->mediaQuota->summaryForUser($user);

            return response()->json([
                'success' => false,
                'message' => 'Storage limit reached. Delete media or clear storage before uploading more.',
                'error_code' => 'media_storage_limit_reached',
                'storage' => $summary,
            ], 422);
        }

        $path = FileUploadUtil::store($file, $subDir);

        $media = MediaFile::create([
            'user_id'       => (int) $user->id,
            'file_name'     => basename($path),
            'original_name' => $originalName,
            'disk'          => 'local',
            'path'          => $path,
            'mime_type'     => $mime,
            'size_bytes'    => $sizeBytes,
            'type'          => $type,
        ]);

        app(UserCacheVersionService::class)->bump((int) $user->id);

        return response()->json([
            'success' => true,
            'message' => 'File uploaded.',
            'media'   => $media,
        ], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();

        $media = MediaFile::query()
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $media->posts()->detach();
        if (is_string($media->path) && trim($media->path) !== '') {
            FileUploadUtil::delete($media->path);
        }
        $media->delete();
        app(UserCacheVersionService::class)->bump((int) $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Media deleted.',
            'storage' => $this->mediaQuota->summaryForUser($user),
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'ids' => 'nullable|array',
            'ids.*' => 'integer',
        ]);

        $query = MediaFile::query()->where('user_id', $user->id);
        if (! empty($validated['ids']) && is_array($validated['ids'])) {
            $query->whereIn('id', array_map('intval', $validated['ids']));
        }

        $rows = $query->get();
        foreach ($rows as $media) {
            $media->posts()->detach();
            if (is_string($media->path) && trim($media->path) !== '') {
                FileUploadUtil::delete($media->path);
            }
            $media->delete();
        }

        app(UserCacheVersionService::class)->bump((int) $user->id);

        return response()->json([
            'success' => true,
            'message' => $rows->isEmpty() ? 'Nothing to clear.' : 'Storage updated.',
            'deleted_count' => $rows->count(),
            'storage' => $this->mediaQuota->summaryForUser($user),
        ]);
    }
}
