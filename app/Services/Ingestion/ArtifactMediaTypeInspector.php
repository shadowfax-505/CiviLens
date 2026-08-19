<?php

namespace App\Services\Ingestion;

use App\Exceptions\Ingestion\AcquisitionFailed;
use finfo;

class ArtifactMediaTypeInspector
{
    public function inspect(string $declaredMediaType, string $content): string
    {
        $detected = strtolower((new finfo(FILEINFO_MIME_TYPE))->buffer($content) ?: 'application/octet-stream');

        if ($this->isExecutable($detected)) {
            throw new AcquisitionFailed('Executable source artifacts are not accepted.');
        }

        if ($declaredMediaType === 'application/octet-stream') {
            return in_array($detected, config('civiclens.ingestion.allowed_media_types', []), true)
                ? $detected
                : $declaredMediaType;
        }

        if ($declaredMediaType === $detected || $this->isCompatible($declaredMediaType, $detected)) {
            return $declaredMediaType;
        }

        throw new AcquisitionFailed('Source media type does not match the artifact content.');
    }

    private function isCompatible(string $declared, string $detected): bool
    {
        // A structured-syntax suffix says what the payload is: RFC 6839 defines
        // application/*+json as JSON, and a query service answering
        // application/sparql-results+json is serving JSON that finfo reports as
        // plain text. Matching the suffix rather than listing every such type
        // keeps the next one from being a special case.
        $textLike = in_array($declared, ['text/html', 'text/csv', 'text/xml', 'application/json', 'application/xml'], true)
            || str_ends_with($declared, '+json')
            || str_ends_with($declared, '+xml');

        if ($textLike) {
            return in_array($detected, ['text/plain', 'application/json', 'application/xml'], true);
        }

        if (in_array($declared, [
            'application/msword',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ], true)) {
            return in_array($detected, ['application/zip', 'application/x-ole-storage'], true);
        }

        return false;
    }

    private function isExecutable(string $mediaType): bool
    {
        return in_array($mediaType, [
            'application/x-dosexec',
            'application/x-executable',
            'application/x-elf',
            'application/x-mach-binary',
            'application/x-msdownload',
            'application/x-php',
        ], true);
    }
}
