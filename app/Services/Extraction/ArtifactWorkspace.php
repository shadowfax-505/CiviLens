<?php

namespace App\Services\Extraction;

use App\Exceptions\Extraction\ExtractionFailed;
use App\Models\SourceArtifactVersion;
use Illuminate\Support\Facades\Storage;

class ArtifactWorkspace
{
    public function __construct(private readonly WordDocumentConverter $wordDocuments) {}

    /**
     * Copy a stored artifact to a short-lived private file and return its path.
     *
     * Artifacts live on a private disk that may not be local, so every consumer
     * works against a copy rather than assuming a filesystem path. The caller owns
     * the file and must pass it back to discard().
     *
     * A Word document is rendered to PDF here rather than at each consumer.
     * Every consumer asks this method for a path and then rasterises it, so
     * converting once at the door is what lets those documents be read at all
     * — but see WordDocumentConverter: the page image a reader is then shown
     * is our rendering of the publisher's file, not the publisher's own.
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

        if ($this->wordDocuments->handles($artifact->media_type)) {
            try {
                $converted = $this->wordDocuments->pdfFor($artifact, $temporaryPath);
            } finally {
                @unlink($temporaryPath);
            }

            return $converted;
        }

        return $temporaryPath;
    }

    /**
     * The media type of what materialize() actually hands back.
     *
     * Callers that dispatch on type must ask this rather than reading
     * `media_type` off the record, or a converted document is looked up under
     * the format it arrived in and refused by a pipeline that could now read it.
     */
    public function effectiveMediaType(SourceArtifactVersion $artifact): string
    {
        return $this->wordDocuments->handles($artifact->media_type)
            ? 'application/pdf'
            : $artifact->media_type;
    }

    public function discard(?string $path): void
    {
        if ($path !== null) {
            @unlink($path);
        }
    }
}
