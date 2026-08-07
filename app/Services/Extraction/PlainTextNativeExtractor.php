<?php

namespace App\Services\Extraction;

use App\Contracts\Extraction\NativeTextExtractor;
use App\Data\Extraction\ExtractedPage;
use App\Data\Extraction\NativeExtractionResult;
use App\Exceptions\Extraction\ExtractionFailed;

class PlainTextNativeExtractor implements NativeTextExtractor
{
    public function supports(string $mediaType): bool
    {
        $registry = config('civiclens.extraction.native_media_types', []);

        return is_array($registry) && ($registry[$mediaType] ?? null) === 'text';
    }

    public function extract(string $absolutePath): NativeExtractionResult
    {
        $startedAt = microtime(true);
        $contents = @file_get_contents($absolutePath);

        if ($contents === false) {
            throw new ExtractionFailed('Native extraction could not read the document.');
        }

        $text = mb_check_encoding($contents, 'UTF-8')
            ? $contents
            : mb_convert_encoding($contents, 'UTF-8', 'UTF-8');

        return new NativeExtractionResult(
            [new ExtractedPage(
                1,
                $text,
                (float) config('civiclens.extraction.default_page_width_points', 595.276),
                (float) config('civiclens.extraction.default_page_height_points', 841.89),
            )],
            'native-text',
            '1',
            (int) round((microtime(true) - $startedAt) * 1000),
        );
    }
}
