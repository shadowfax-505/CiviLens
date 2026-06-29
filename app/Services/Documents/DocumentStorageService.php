<?php

namespace App\Services\Documents;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DocumentStorageService
{
    /**
     * @return array<string, int|string>
     */
    public function store(UploadedFile $file): array
    {
        $disk = (string) config('civiclens.documents.disk', 'local');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $storedFilename = Str::uuid().'.'.$extension;
        $directory = 'documents/'.now()->format('Y/m');
        $path = $file->storeAs($directory, $storedFilename, $disk);
        $realPath = $file->getRealPath();

        if ($path === false || $realPath === false) {
            throw new RuntimeException('Unable to store uploaded document.');
        }

        $checksum = hash_file('sha256', $realPath);

        if ($checksum === false) {
            throw new RuntimeException('Unable to checksum uploaded document.');
        }

        return [
            'original_filename' => basename($file->getClientOriginalName()),
            'stored_filename' => $storedFilename,
            'storage_disk' => $disk,
            'storage_path' => $path,
            'file_extension' => $extension,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'checksum' => $checksum,
            'file_size' => (int) $file->getSize(),
        ];
    }

    public function exists(string $disk, string $path): bool
    {
        return Storage::disk($disk)->exists($path);
    }
}
