<?php

namespace App\Services\Extraction;

use App\Models\DiscoveredResource;
use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\SourceArtifactVersion;
use App\Models\SourceEndpoint;
use App\Models\SourcePublisher;
use Illuminate\Support\Str;

/**
 * Turn extracted pages into items a person can judge.
 *
 * Nothing created extraction fields from live documents. BenchmarkEvaluationService
 * only creates them where a gold value already exists, so the calibration set
 * could never grow beyond a fixture, and the conformal bound had nothing to be
 * computed from.
 *
 * The unit is a numeric token — an amount, a date, a reference number — because
 * that is what audit reports actually contain. They carry no labelled fields at
 * all: sixty pages were sampled and not one held three label-value pairs. A
 * token is also decomposable, so a bound computed over tokens stays valid, which
 * a bound over whole-page character accuracy would not.
 *
 * Every candidate is created with no gold value and no outcome. A generator that
 * decided correctness would be marking its own homework, and the guarantee
 * computed from it would be vacuous.
 */
class ReviewCandidateGenerator
{
    /**
     * Amounts, dates and reference numbers as these documents write them,
     * including Bengali digits, which appear throughout the audit corpus.
     */
    private const PATTERNS = [
        // Day and month bounded, or a reference like 44.07.9000 reads as a date.
        'date' => '/\b(0?[1-9]|[12]\d|3[01])[.\-\/](0?[1-9]|1[0-2])[.\-\/](\d{4}|\d{2})\b/u',
        'amount' => '/(?<![\w.])[\d\x{09E6}-\x{09EF}][\d\x{09E6}-\x{09EF},]{2,}(?:\.\d{1,2})?(?![\w])/u',
        'reference' => '/\b[\d\x{09E6}-\x{09EF}]{2,}(?:\.[\d\x{09E6}-\x{09EF}]{2,}){2,}\b/u',
    ];

    /**
     * @return array{created: int, pages: int, skipped_pages: int}
     */
    public function generate(int $limit = 200): array
    {
        $created = 0;
        $pages = 0;
        $skipped = 0;

        $candidates = ExtractionPage::query()
            ->with('run')
            ->whereNotNull('extracted_text')
            // A page the recognizer would not vouch for is not worth a
            // reviewer's attention: they would be adjudicating text the system
            // has already declined to stand behind.
            ->where('extraction_path', '!=', SelectiveOcrService::ABSTAINED)
            ->where('character_count', '>', 50)
            ->inRandomOrder()
            ->limit(max(1, $limit))
            ->get();

        foreach ($candidates as $page) {
            $pages++;
            $before = $created;

            foreach ($this->tokens((string) $page->extracted_text) as $token) {
                if (ExtractionField::query()
                    ->where('extraction_page_id', $page->getKey())
                    ->where('evidence_offset_start', $token['start'])
                    ->exists()) {
                    continue;
                }

                ExtractionField::query()->create([
                    'extraction_run_id' => $page->extraction_run_id,
                    'extraction_page_id' => $page->getKey(),
                    'field_key' => $token['kind'],
                    'field_type' => 'string',
                    'extracted_value' => $token['value'],
                    'normalized_value' => Str::of($token['value'])->replace(',', '')->trim()->value(),
                    'script_class' => $page->script_class ?? 'unknown',
                    'publisher_group' => $this->publisherGroup($page),
                    'confidence' => $page->confidence,
                    // Lower is more conforming, matching the benchmark path so a
                    // reviewer-labelled field and a benchmark one are comparable.
                    'nonconformity_score' => $page->confidence === null
                        ? 1.0
                        : round(max(0.0, min(1.0, 1.0 - ((float) $page->confidence / 100))), 8),
                    'decision' => 'pending',
                    'evidence_page_number' => $page->page_number,
                    'evidence_offset_start' => $token['start'],
                    'evidence_offset_end' => $token['start'] + mb_strlen($token['value']),
                    'gold_source' => null,
                ]);

                $created++;
            }

            if ($created === $before) {
                $skipped++;
            }
        }

        return ['created' => $created, 'pages' => $pages, 'skipped_pages' => $skipped];
    }

    /**
     * @return list<array{kind: string, value: string, start: int}>
     */
    public function tokens(string $text): array
    {
        $found = [];
        $claimed = [];

        // Dates first, then references, then amounts. A date claimed as a
        // reference is a mislabelled item a reviewer then has to argue with,
        // and an amount would otherwise swallow half of either.
        foreach (['date', 'reference', 'amount'] as $kind) {
            if (preg_match_all(self::PATTERNS[$kind], $text, $matches, PREG_OFFSET_CAPTURE) === false) {
                continue;
            }

            foreach ($matches[0] as $match) {
                $start = mb_strlen(substr($text, 0, $match[1]));
                $end = $start + mb_strlen($match[0]);

                foreach ($claimed as [$claimedStart, $claimedEnd]) {
                    if ($start < $claimedEnd && $end > $claimedStart) {
                        continue 2;
                    }
                }

                $claimed[] = [$start, $end];
                $found[] = ['kind' => $kind, 'value' => $match[0], 'start' => $start];
            }
        }

        return $found;
    }

    /**
     * Which publisher a page came from, since the bound is calibrated per group.
     *
     * A run with no source artifact is a benchmark run, and grouping it with a
     * live publisher would mix two populations under one guarantee.
     */
    private function publisherGroup(ExtractionPage $page): string
    {
        $artifact = $page->run?->artifactVersion;
        $resource = $artifact instanceof SourceArtifactVersion ? $artifact->discoveredResource : null;
        $endpoint = $resource instanceof DiscoveredResource ? $resource->endpoint : null;
        $publisher = $endpoint instanceof SourceEndpoint ? $endpoint->publisher : null;
        $slug = $publisher instanceof SourcePublisher ? $publisher->slug : null;

        return is_string($slug) && $slug !== '' ? $slug : 'unattributed';
    }
}
