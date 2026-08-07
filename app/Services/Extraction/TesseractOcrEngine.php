<?php

namespace App\Services\Extraction;

use App\Contracts\Extraction\OcrEngine;
use App\Data\Extraction\OcrPageResult;
use App\Exceptions\Extraction\ExtractionFailed;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class TesseractOcrEngine implements OcrEngine
{
    private const CONFIDENCE_COLUMN = 10;

    private const TEXT_COLUMN = 11;

    public function recognize(string $imagePath, int $dpi, int $pageSegmentationMode): OcrPageResult
    {
        $startedAt = microtime(true);
        $languages = (string) config('civiclens.extraction.ocr.languages', 'ben+eng');

        $tsv = $this->run([
            $this->binary(),
            $imagePath,
            '-',
            '-l', $languages,
            '--psm', (string) $pageSegmentationMode,
            '--dpi', (string) $dpi,
            'tsv',
        ]);

        [$words, $confidences] = $this->parse($tsv);

        return new OcrPageResult(
            implode(' ', $words),
            $confidences === [] ? null : round(array_sum($confidences) / count($confidences), 4),
            count($words),
            'tesseract',
            $this->version(),
            $languages,
            $dpi,
            (int) round((microtime(true) - $startedAt) * 1000),
        );
    }

    /**
     * Tesseract emits one TSV row per layout element; only rows carrying both a
     * non-negative confidence and non-empty text are recognized words. Structural
     * rows report -1 and would drag any mean toward nonsense if averaged in.
     *
     * @return array{list<string>, list<float>}
     */
    private function parse(string $tsv): array
    {
        $words = [];
        $confidences = [];

        foreach (array_slice(preg_split('/\R/', $tsv) ?: [], 1) as $line) {
            $columns = explode("\t", $line);

            if (count($columns) < 12) {
                continue;
            }

            $text = trim($columns[self::TEXT_COLUMN]);
            $confidence = $columns[self::CONFIDENCE_COLUMN];

            if ($text === '' || ! is_numeric($confidence) || (float) $confidence < 0) {
                continue;
            }

            $words[] = $text;
            $confidences[] = (float) $confidence;
        }

        return [$words, $confidences];
    }

    private function binary(): string
    {
        return (string) config('civiclens.extraction.ocr.tesseract_binary', 'tesseract');
    }

    private function version(): string
    {
        try {
            $output = $this->run([$this->binary(), '--version'], true);
        } catch (ExtractionFailed) {
            return 'unknown';
        }

        return preg_match('/tesseract\s+([\w.\-]+)/i', $output, $matches) === 1 ? $matches[1] : 'unknown';
    }

    /** @param list<string> $command */
    private function run(array $command, bool $allowStderr = false): string
    {
        $process = new Process($command);
        $process->setTimeout((float) config('civiclens.extraction.ocr.timeout_seconds', 120));

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw new ExtractionFailed('OCR exceeded the configured time limit.');
        }

        if (! $process->isSuccessful()) {
            throw new ExtractionFailed('OCR could not read the page image.');
        }

        $output = $process->getOutput();

        return $allowStderr && trim($output) === '' ? $process->getErrorOutput() : $output;
    }
}
