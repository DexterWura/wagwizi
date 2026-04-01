<?php

namespace App\Utils;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class FileUploadUtil
{
    public static function store(UploadedFile $file, string $subDirectory): string
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $destination = public_path('assets/uploads/' . $subDirectory);

        $file->move($destination, $filename);

        return 'assets/uploads/' . $subDirectory . '/' . $filename;
    }

    public static function delete(string $path): bool
    {
        $fullPath = public_path($path);

        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }

        return false;
    }
}
