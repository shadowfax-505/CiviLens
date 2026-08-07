<?php

namespace App\Services\Extraction;

use App\Contracts\Extraction\NativeTextExtractor;
use App\Data\Extraction\ExtractedPage;
use App\Data\Extraction\NativeExtractionResult;
use App\Exceptions\Extraction\ExtractionFailed;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class PdfNativeTextExtractor implements NativeTextExtractor
{
    public function supports(string $mediaType): bool
    {
        return $mediaType === 'application/pdf';
    }

    public function extract(string $absolutePath): NativeExtractionResult
    {
        $startedAt = microtime(true);
        [$pageCount, $width, $height] = $this->pageGeometry($absolutePath);

        if ($pageCount > (int) config('civiclens.extraction.max_pages', 500)) {
            throw new ExtractionFailed('Document exceeds the configured page limit.');
        }

        $text = $this->run([
            (string) config('civiclens.extraction.pdftotext_binary', 'pdftotext'),
            '-enc', 'UTF-8',
            '-q',
            $absolutePath,
            '-',
        ]);

        $pages = [];

        foreach ($this->splitPages($text, $pageCount) as $index => $pageText) {
            $pages[] = new ExtractedPage($index + 1, $pageText, $width, $height);
        }

        return new NativeExtractionResult(
            $pages,
            'poppler',
            $this->version(),
            (int) round((microtime(true) - $startedAt) * 1000),
        );
    }

    /**
     * pdftotext separates pages with a form feed. A trailing feed produces an empty
     * final element, and a PDF whose pages are all image-only produces no feeds at
     * all, so the page count from pdfinfo is authoritative.
     *
     * @return list<string>
     */
    private function splitPages(string $text, int $pageCount): array
    {
        $pages = explode("\f", $text);

        while (count($pages) > $pageCount && trim((string) end($pages)) === '') {
            array_pop($pages);
        }

        while (count($pages) < $pageCount) {
            $pages[] = '';
        }

        return array_values(array_slice($pages, 0, max($pageCount, 1)));
    }

    /** @return array{int, float, float} */
    private function pageGeometry(string $absolutePath): array
    {
        $output = $this->run([
            (string) config('civiclens.extraction.pdfinfo_binary', 'pdfinfo'),
            $absolutePath,
        ]);

        $pageCount = 0;
        $width = (float) config('civiclens.extraction.default_page_width_points', 595.276);
        $height = (float) config('civiclens.extraction.default_page_height_points', 841.89);

        if (preg_match('/^Pages:\s+(\d+)/m', $output, $matches) === 1) {
            $pageCount = (int) $matches[1];
        }

        if (preg_match('/^Page size:\s+([\d.]+) x ([\d.]+)/m', $output, $matches) === 1) {
            $width = (float) $matches[1];
            $height = (float) $matches[2];
        }

        if ($pageCount < 1) {
            throw new ExtractionFailed('Document did not report a readable page count.');
        }

        return [$pageCount, $width, $height];
    }

    private function version(): string
    {
        try {
            $output = $this->run([(string) config('civiclens.extraction.pdftotext_binary', 'pdftotext'), '-v'], true);
        } catch (ExtractionFailed) {
            return 'unknown';
        }

        return preg_match('/version\s+([\w.\-]+)/i', $output, $matches) === 1 ? $matches[1] : 'unknown';
    }

    /** @param list<string> $command */
    private function run(array $command, bool $allowStderr = false): string
    {
        $process = new Process($command);
        $process->setTimeout((float) config('civiclens.extraction.process_timeout_seconds', 60));

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw new ExtractionFailed('Native extraction exceeded the configured time limit.');
        }

        if (! $process->isSuccessful()) {
            throw new ExtractionFailed('Native extraction could not read the document.');
        }

        $output = $process->getOutput();

        return $allowStderr && trim($output) === '' ? $process->getErrorOutput() : $output;
    }
}
