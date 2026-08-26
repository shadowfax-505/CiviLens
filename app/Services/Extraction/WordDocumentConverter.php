<?php

namespace App\Services\Extraction;

use App\Exceptions\Extraction\ExtractionFailed;
use App\Models\SourceArtifactVersion;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Render a Word document into the PDF this pipeline knows how to read.
 *
 * Everything downstream reads a document by rasterising its pages and looking
 * at them: OCR, the second read, table detection, the ink measurement behind
 * abstention, and the page image a reader is shown as evidence. A Word file has
 * no pages until something lays it out, so 13 IMED documents were acquired and
 * never read.
 *
 * The conversion is cached against the artifact's own checksum. Materialising
 * happens once per page per pass and there are five passes; converting each
 * time would cost more than the reading does.
 *
 * **What a reader is shown changes for these documents, and that has to be
 * said.** For a PDF, the page image is the publisher's own file rendered. For
 * one of these it is LibreOffice's rendering of the publisher's file, and the
 * layout may differ from what the publisher saw. The evidence is still the
 * document's content; it is no longer the document's appearance.
 */
class WordDocumentConverter
{
    public const MEDIA_TYPES = [
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /** Conversion is slow and single-threaded; a stuck one must not hold a worker. */
    private const TIMEOUT_SECONDS = 180;

    public function handles(string $mediaType): bool
    {
        return in_array(strtolower($mediaType), self::MEDIA_TYPES, true);
    }

    public function isAvailable(): bool
    {
        $binary = (string) config('civiclens.extraction.soffice_binary', 'soffice');

        $which = new Process(['which', $binary]);
        $which->run();

        return $which->isSuccessful();
    }

    /**
     * The path of a PDF rendering of this artifact, converting it if needed.
     */
    public function pdfFor(SourceArtifactVersion $artifact, string $sourcePath): string
    {
        $disk = Storage::disk('local');
        $cacheKey = 'converted-pdf/'.$artifact->sha256.'.pdf';

        if ($disk->exists($cacheKey)) {
            return $this->copyOut($disk->path($cacheKey));
        }

        if (! $this->isAvailable()) {
            throw new ExtractionFailed('LibreOffice is not configured, so Word documents cannot be read.');
        }

        $workingDirectory = sys_get_temp_dir().'/civiclens-convert-'.$artifact->getKey();

        if (! is_dir($workingDirectory) && ! mkdir($workingDirectory, 0700, true) && ! is_dir($workingDirectory)) {
            throw new ExtractionFailed('Unable to prepare a working directory for conversion.');
        }

        // LibreOffice names the output after the input, so the input needs the
        // extension its format implies or it refuses to load the file at all.
        $named = $workingDirectory.'/source'.$this->extensionFor($artifact->media_type);
        copy($sourcePath, $named);

        $process = new Process([
            (string) config('civiclens.extraction.soffice_binary', 'soffice'),
            '--headless',
            '--convert-to', 'pdf',
            '--outdir', $workingDirectory,
            $named,
        ]);

        $process->setTimeout(self::TIMEOUT_SECONDS);
        $process->run();

        $converted = $workingDirectory.'/source.pdf';

        if (! $process->isSuccessful() || ! is_file($converted)) {
            $this->cleanUp($workingDirectory);

            throw new ExtractionFailed('LibreOffice could not render this document.');
        }

        $handle = fopen($converted, 'rb');

        if (is_resource($handle)) {
            $disk->writeStream($cacheKey, $handle);
            fclose($handle);
        }

        $result = $this->copyOut($converted);

        $this->cleanUp($workingDirectory);

        return $result;
    }

    private function extensionFor(string $mediaType): string
    {
        return strtolower($mediaType) === 'application/msword' ? '.doc' : '.docx';
    }

    /** A private copy the caller owns and discards, as materialize() promises. */
    private function copyOut(string $path): string
    {
        $destination = tempnam(sys_get_temp_dir(), 'civiclens-extraction-');

        if ($destination === false || ! copy($path, $destination)) {
            throw new ExtractionFailed('Unable to prepare the converted document for extraction.');
        }

        chmod($destination, 0600);

        return $destination;
    }

    private function cleanUp(string $directory): void
    {
        foreach (glob($directory.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($directory);
    }
}
