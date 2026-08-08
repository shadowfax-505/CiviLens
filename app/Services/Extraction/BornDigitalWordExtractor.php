<?php

namespace App\Services\Extraction;

use App\Data\Extraction\RecognizedWord;
use App\Exceptions\Extraction\ExtractionFailed;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Read exact word geometry from a born-digital PDF's text layer.
 *
 * No OCR and no recognizer, so there is nothing to be uncertain about: the
 * characters are the ones the producer embedded. Every word is therefore
 * reported at confidence 100.
 *
 * That uniform confidence is deliberately useless as a nonconformity score, and
 * must never be used as one. A score that is constant across every field ranks
 * nothing, and a calibration built on it would certify by accident. Ordering for
 * born-digital extraction has to come from structural signals -- label-match
 * exactness, layout distance, format conformance, cross-document agreement --
 * not from a confidence that does not exist.
 */
class BornDigitalWordExtractor
{
    public const EXACT_CONFIDENCE = 100.0;

    /**
     * @return list<RecognizedWord>
     */
    public function words(string $pdfPath, int $pageNumber): array
    {
        $process = new Process([
            (string) config('civiclens.extraction.pdftotext_binary', 'pdftotext'),
            '-bbox',
            '-q',
            '-f', (string) $pageNumber,
            '-l', (string) $pageNumber,
            $pdfPath,
            '-',
        ]);
        $process->setTimeout((float) config('civiclens.extraction.process_timeout_seconds', 60));

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw new ExtractionFailed('Born-digital extraction exceeded the configured time limit.');
        }

        if (! $process->isSuccessful()) {
            throw new ExtractionFailed('Born-digital extraction could not read the document.');
        }

        return $this->parse($process->getOutput());
    }

    /**
     * @return list<RecognizedWord>
     */
    private function parse(string $xhtml): array
    {
        $matched = preg_match_all(
            '/<word xMin="([\d.\-]+)" yMin="([\d.\-]+)" xMax="([\d.\-]+)" yMax="([\d.\-]+)">(.*?)<\/word>/s',
            $xhtml,
            $matches,
            PREG_SET_ORDER,
        );

        if ($matched === false || $matched === 0) {
            return [];
        }

        $words = [];

        foreach ($matches as $match) {
            $text = trim(html_entity_decode($match[5], ENT_QUOTES | ENT_XML1, 'UTF-8'));

            if ($text === '') {
                continue;
            }

            $left = (int) round((float) $match[1]);
            $top = (int) round((float) $match[2]);

            $words[] = new RecognizedWord(
                $text,
                self::EXACT_CONFIDENCE,
                $left,
                $top,
                max(1, (int) round((float) $match[3]) - $left),
                max(1, (int) round((float) $match[4]) - $top),
            );
        }

        return $words;
    }
}
