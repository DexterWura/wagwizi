<?php

namespace App\Controllers;

use App\Models\MediaFile;
use App\Utils\FileUploadUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MediaController extends Controller
{
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

        $file = $request->file('file');
        // Read metadata before move(): move() deletes the temp file; getSize() would stat a missing path.
        $mime          = $file->getMimeType();
        $sizeBytes     = $file->getSize();
        $originalName  = $file->getClientOriginalName();
        $type          = str_starts_with((string) $mime, 'video/') ? 'video' : 'image';
        $subDir        = $type === 'video' ? 'videos' : 'images';

        $path = FileUploadUtil::store($file, $subDir);

        $media = MediaFile::create([
            'user_id'       => Auth::id(),
            'file_name'     => basename($path),
            'original_name' => $originalName,
            'disk'          => 'local',
            'path'          => $path,
            'mime_type'     => $mime,
            'size_bytes'    => $sizeBytes,
            'type'          => $type,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File uploaded.',
            'media'   => $media,
        ], 201);
    }
}
