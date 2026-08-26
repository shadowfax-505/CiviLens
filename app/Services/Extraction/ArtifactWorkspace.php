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

        // Streamed rather than read whole. `$disk->get()` returns the entire
        // file as a string, so materialising a 46 MB report cost 46 MB of the
        // 128 MB this process is given — and scoring the corpus died at page
        // 2,500 of 2,979 doing exactly that, after the byte limit was raised to
        // let documents that size in.
        //
        // The consumers all want a path on disk. None of them wants the bytes.
        $source = $disk->readStream($artifact->storage_path);

        if (! is_resource($source)) {
            @unlink($temporaryPath);

            throw new ExtractionFailed('Unable to prepare the artifact for extraction.');
        }

        $destination = fopen($temporaryPath, 'wb');

        if ($destination === false) {
            fclose($source);
            @unlink($temporaryPath);

            throw new ExtractionFailed('Unable to prepare the artifact for extraction.');
        }

        $copied = stream_copy_to_stream($source, $destination);

        fclose($source);
        fclose($destination);

        if ($copied === false) {
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
