<?php

namespace App\Services\Extraction;

use App\Contracts\Extraction\OcrEngine;
use App\Data\Extraction\OcrPageResult;
use App\Data\Extraction\RecognizedWord;
use App\Exceptions\Extraction\ExtractionFailed;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class TesseractOcrEngine implements OcrEngine
{
    private const LEFT_COLUMN = 6;

    private const TOP_COLUMN = 7;

    private const WIDTH_COLUMN = 8;

    private const HEIGHT_COLUMN = 9;

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

        $words = $this->parse($tsv);
        $words = array_map(
            fn (RecognizedWord $word): RecognizedWord => new RecognizedWord(
                $this->utf8($word->text),
                $word->confidence,
                $word->left,
                $word->top,
                $word->width,
                $word->height,
            ),
            $words,
        );
        $confidences = array_map(fn (RecognizedWord $word): float => $word->confidence, $words);

        return new OcrPageResult(
            $words,
            implode(' ', array_map(fn (RecognizedWord $word): string => $word->text, $words)),
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
     * @return list<RecognizedWord>
     */
    private function parse(string $tsv): array
    {
        $words = [];

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

            $words[] = new RecognizedWord(
                $text,
                (float) $confidence,
                (int) $columns[self::LEFT_COLUMN],
                (int) $columns[self::TOP_COLUMN],
                (int) $columns[self::WIDTH_COLUMN],
                (int) $columns[self::HEIGHT_COLUMN],
            );
        }

        return $words;
    }

    private function binary(): string
    {
        return (string) config('civiclens.extraction.ocr.tesseract_binary', 'tesseract');
    }

    /**
     * Drop byte sequences that are not valid UTF-8.
     *
     * Tesseract emits a few invalid bytes on Bengali pages, and a single one
     * poisons the whole string: every preg_* call with the /u modifier returns
     * null or false on it, without warning. Measured on a real audit report
     * page, the engine recognised 222 words while ExtractedPage::wordCount()
     * reported 0, because preg_split silently failed. Any routing or quality
     * decision reading that count would have been made on a zero.
     *
     * The characters themselves are fine — the sample renders as ordinary
     * Bengali once the invalid bytes are removed — so dropping them keeps the
     * text and loses only what was never readable.
     */
    private function utf8(string $text): string
    {
        if (mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }

        return mb_convert_encoding($text, 'UTF-8', 'UTF-8');
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
