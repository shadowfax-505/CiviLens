<?php

namespace App\Services\Extraction;

use App\Exceptions\Extraction\ExtractionFailed;
use JsonException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Ask the sidecar where a page's tables and cells are.
 *
 * Structure only. The sidecar returns no text, because PaddleOCR's recognizer
 * has no established quality on Bengali and this corpus is mostly Bengali;
 * letting it decide what a figure says would silently change every page. Text
 * comes from Tesseract words placed into these boxes.
 *
 * Optional at runtime. A machine without the sidecar installed keeps working and
 * its pages simply carry no table structure, which is why a missing interpreter
 * reports "unavailable" rather than failing extraction.
 */
class TableStructureDetector
{
    /**
     * @return list<array{index: int, cells: list<array{row: int, col: int, row_span: int, col_span: int, box: list<int>, model_text: string}>}>
     *
     * @throws ExtractionFailed
     */
    public function detect(string $imagePath): array
    {
        $python = (string) config('civiclens.extraction.tables.python', '');
        $script = (string) config('civiclens.extraction.tables.script', base_path('tools/table-structure/detect_tables.py'));

        if ($python === '' || ! is_executable($python) || ! is_readable($script)) {
            throw new ExtractionFailed('Table structure detection is not available on this machine.');
        }

        $process = new Process([$python, $script, $imagePath]);
        $process->setTimeout((float) config('civiclens.extraction.tables.timeout_seconds', 300));

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw new ExtractionFailed('Table structure detection timed out.');
        }

        if (! $process->isSuccessful()) {
            throw new ExtractionFailed('Table structure detection failed: '.trim($process->getErrorOutput()));
        }

        try {
            $payload = json_decode($process->getOutput(), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ExtractionFailed('Table structure detection returned unreadable output.');
        }

        // A page with no table is a result, not a failure: most pages in this
        // corpus are prose, and treating that as an error would bury the pages
        // that did fail.
        return is_array($payload) && is_array($payload['tables'] ?? null) ? array_values($payload['tables']) : [];
    }

    public function isAvailable(): bool
    {
        $python = (string) config('civiclens.extraction.tables.python', '');

        return $python !== '' && is_executable($python);
    }
}
