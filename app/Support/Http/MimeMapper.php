<?php

namespace App\Support\Http;

class MimeMapper
{
    private const MAP = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
    ];

    public static function fromExtension(string $extension): string
    {
        return self::MAP[strtolower(ltrim($extension, '.'))] ?? 'application/octet-stream';
    }

    /**
     * @param  array<int, string>  $extensions
     */
    public static function toAcceptString(array $extensions): string
    {
        return collect($extensions)
            ->flatMap(fn (string $extension): array => ['.'.ltrim($extension, '.'), self::fromExtension($extension)])
            ->filter()
            ->unique()
            ->implode(',');
    }

    /**
     * @param  array<int, string>  $extensions
     */
    public static function describe(array $extensions): string
    {
        return collect($extensions)
            ->map(fn (string $extension): string => strtoupper(ltrim($extension, '.')))
            ->join(', ');
    }
}
