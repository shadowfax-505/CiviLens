<?php

namespace App\Services\Extraction;

use App\Exceptions\Extraction\ExtractionFailed;
use App\Models\SourceArtifactVersion;
use Illuminate\Support\Facades\Storage;

class ArtifactWorkspace
{
    /**
     * Copy a stored artifact to a short-lived private file and return its path.
     *
     * Artifacts live on a private disk that may not be local, so every consumer
     * works against a copy rather than assuming a filesystem path. The caller owns
     * the file and must pass it back to discard().
     */
    public function materialize(SourceArtifactVersion $artifact): string
    {
        if ($artifact->is_quarantined) {
            throw new ExtractionFailed('Quarantined artifacts are not eligible for extraction.');
        }

        $disk = Storage::disk($artifact->storage_disk);

        if (! $disk->exists($artifact->storage_path)) {
            throw new ExtractionFailed('Stored artifact is no longer available.');
        }

        if ($artifact->byte_size > (int) config('civiclens.extraction.max_bytes', 52428800)) {
            throw new ExtractionFailed('Artifact exceeds the configured extraction byte limit.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'civiclens-extraction-');

        if ($temporaryPath === false) {
            throw new ExtractionFailed('Unable to prepare the artifact for extraction.');
        }

        chmod($temporaryPath, 0600);

        if (file_put_contents($temporaryPath, $disk->get($artifact->storage_path), LOCK_EX) === false) {
            @unlink($temporaryPath);

            throw new ExtractionFailed('Unable to prepare the artifact for extraction.');
        }

        return $temporaryPath;
    }

    public function discard(?string $path): void
    {
        if ($path !== null) {
            @unlink($path);
        }
    }
}
