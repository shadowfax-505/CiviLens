<?php

namespace App\Services\Extraction;

use App\Models\DiscoveredResource;
use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\ExtractionRun;
use App\Models\ExtractionTableCell;
use App\Models\ScreeningEntity;
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
     * Most a cell may hold and still be treated as holding a value.
     *
     * Two rather than one so a figure carrying a unit or a bracketed sign stays
     * eligible, and well below the three-to-seven words seen in cells that were
     * prose rather than table.
     */
    /**
     * Pages held in memory at once.
     *
     * Each carries its whole extracted text, so this is the only thing standing
     * between a corpus that has grown five-fold and the 128 MB this command is
     * given. Loading them all exhausted it at 8,900 pages.
     */
    private const PAGES_PER_BATCH = 200;

    private const MAX_VALUE_CELL_WORDS = 2;

    /**
     * Acquired, archived, and not a document anybody publishes as one.
     *
     * HTML is the site around a document; JSON is a record feed archived for
     * provenance. Both belong in the corpus and neither belongs in a queue that
     * asks whether characters were read correctly.
     */
    private const NON_DOCUMENT_MEDIA_TYPES = ['text/html', 'application/json', 'text/plain'];

    /**
     * The amount pattern, so a caller can ask what this class would produce
     * without duplicating the expression and drifting from it.
     */
    public static function amountPattern(): string
    {
        return self::PATTERNS['amount'];
    }

    public function __construct(
        private readonly BengaliTextPlausibility $plausibility,
        private readonly AmountGrouping $grouping,
    ) {}

    /**
     * Amounts, dates and reference numbers as these documents write them,
     * including Bengali digits, which appear throughout the audit corpus.
     */
    private const PATTERNS = [
        // Day and month bounded, or a reference like 44.07.9000 reads as a date.
        'date' => '/\b(0?[1-9]|[12]\d|3[01])[.\-\/](0?[1-9]|1[0-2])[.\-\/](\d{4}|\d{2})\b/u',
        // A grouping comma or a decimal, or at least five digits. Without that
        // every bare year matched: "2016" was queued as an Amount, and a
        // reviewer then had to decide whether a correctly read year is a
        // correctly read amount, which is not the question being asked.
        // The sign is part of the figure. Read without it, a reappropriation of
        // -100.00 crore is recorded as +100.00 and the reviewer is shown a value
        // the page does not contain. Accounting parentheses mean the same thing
        // and are captured the same way, as written rather than interpreted.
        // A minus directly after a digit is a range, not a sign: 2013-2017 must
        // not yield -2017, so the sign is only taken where no digit precedes it.
        // A match may not begin immediately after a comma, or end immediately
        // before a decimal digit. Without those two guards the pattern read
        // fragments of numbers it could not match whole: ৬.০২,৪৫,৩৯৫ — a
        // thousands separator the recognizer read as a point — yielded
        // ৪৫,৩৯৫, and the section number ৩.৬.৭ yielded ৩.৬. Both are values
        // the page does not contain, and a reviewer shown one is being asked
        // about a figure that is not there.
        //
        // The fractional part takes the same digits as the integer part.
        // Accepting Bengali digits before the point and only Latin ones after
        // it is what sent ৬.০২ down this path to begin with.
        //
        // A minus after a closing bracket is not a sign. These reports write a
        // difference as (১,৫৯৪.৫২-১,১১৬.৮২)-৪৭৭.৭০, where the last minus
        // introduces the result; reading it as negative reverses the figure.
        'amount' => '/(?<![\w.,])(?:\((?:[-\x{2212}]\x{0020}?)?(?:[\d\x{09E6}-\x{09EF}]{1,3}(?:[,][\d\x{09E6}-\x{09EF}]{2,3})+(?:\.[\d\x{09E6}-\x{09EF}]{1,2})?|[\d\x{09E6}-\x{09EF}]+\.[\d\x{09E6}-\x{09EF}]{1,2}|[\d\x{09E6}-\x{09EF}]{5,})\)|(?<![\d\x{09E6}-\x{09EF}\)])[-\x{2212}]?(?:[\d\x{09E6}-\x{09EF}]{1,3}(?:[,][\d\x{09E6}-\x{09EF}]{2,3})+(?:\.[\d\x{09E6}-\x{09EF}]{1,2})?|[\d\x{09E6}-\x{09EF}]+\.[\d\x{09E6}-\x{09EF}]{1,2}|[\d\x{09E6}-\x{09EF}]{5,}))(?![\w])(?!\.[\d\x{09E6}-\x{09EF}])/u',
        'reference' => '/\b[\d\x{09E6}-\x{09EF}]{2,}(?:\.[\d\x{09E6}-\x{09EF}]{2,}){2,}\b/u',
        // Organisations, which is what gives the screener Bangladeshi names to
        // screen at all — it holds 1,401 debarment entities and, until now,
        // nothing from this corpus to compare them against.
        //
        // Only the Messrs form. `মেসার্স` and `M/S` mean "the firm of", so what
        // follows is a firm name and not a description of one. Anchoring on
        // `ব্যাংক` or `কোম্পানি` instead was tried over the whole corpus and
        // produced "বিভিন্ন বাণিজ্যিক ব্যাংক" — "various commercial banks" —
        // and "এবং কর্পোরেশন"; a regex does not find names in Bengali prose,
        // and asking a reviewer to judge those wastes the scarcest thing this
        // project has. This form yields 43 distinct names across the corpus,
        // among them M/S SKYNET SEA FOODS and M/S Argon Denims Ltd., which are
        // the contractors screening is for.
        //
        // The span ends at a particle. `এর` and `কে` mark the noun before them,
        // so what follows is the sentence resuming rather than more of the name.
        'organisation' => '/(?:মেসার্স|মেসাস|M\/S\.?|M\/s\.?)\s+(?:(?!(?:এর|কে|হতে|থেকে|এবং|নং|নামে|নামীয়|at|DT|কর্তৃক|প্রকৃত|বরাবর)\s)[\x{0980}-\x{09FF}A-Za-z][\x{0980}-\x{09FF}A-Za-z0-9.\x{0027}&-]*\s+){0,3}(?!(?:এর|কে|হতে|থেকে|এবং|নং|at|DT)\s)[\x{0980}-\x{09FF}A-Za-z][\x{0980}-\x{09FF}A-Za-z0-9.\x{0027}&-]*/u',
    ];

    /**
     * Candidates taken from table cells, which carry their row and column.
     *
     * Generated from the cell rather than matched to it afterwards. A figure
     * inside a cell knows which row and column it sat in by construction, so the
     * ambiguity that defeats text matching — 81 of 300 sampled values appear
     * more than once on their own page — never arises.
     *
     * @return array{created: int, cells: int}
     */
    public function generateFromCells(int $limit = 500): array
    {
        $created = 0;
        $cells = 0;

        $candidates = ExtractionTableCell::query()
            ->with('page')
            ->whereNotNull('text')
            ->where('word_count', '>', 0)
            // A cell holding a figure holds one word. On the page measured, every
            // value cell held exactly one and the cells holding three to seven
            // were running prose beneath the table that the detector's box had
            // swept in; a token taken from those would carry a row and column it
            // never sat in, which is worse than having no provenance at all.
            ->where('word_count', '<=', self::MAX_VALUE_CELL_WORDS)
            ->inRandomOrder()
            ->limit(max(1, $limit))
            ->get();

        foreach ($candidates as $cell) {
            $cells++;
            $page = $cell->page;

            if (! $page instanceof ExtractionPage) {
                continue;
            }

            foreach ($this->tokens((string) $cell->text) as $token) {
                if (ExtractionField::query()
                    ->where('extraction_table_cell_id', $cell->getKey())
                    ->where('extracted_value', $token['value'])
                    ->exists()) {
                    continue;
                }

                ExtractionField::query()->create([
                    'extraction_run_id' => $page->extraction_run_id,
                    'extraction_page_id' => $page->getKey(),
                    'extraction_table_cell_id' => $cell->getKey(),
                    'field_key' => $token['kind'],
                    'field_type' => 'string',
                    'extracted_value' => $token['value'],
                    'normalized_value' => $token['kind'] === 'amount'
                        ? $this->normalisedAmount($token['value'])
                        : Str::of($token['value'])->replace(',', '')->trim()->value(),
                    'script_class' => $page->script_class ?? 'unknown',
                    'publisher_group' => $this->publisherGroup($page),
                    'confidence' => $page->confidence,
                    'nonconformity_score' => $page->confidence === null
                        ? 1.0
                        : round(max(0.0, min(1.0, 1.0 - ((float) $page->confidence / 100))), 8),
                    'decision' => 'pending',
                    'evidence_page_number' => $page->page_number,
                    // Offsets are within the cell, not the page: the cell is the
                    // context a reviewer is shown.
                    'evidence_offset_start' => $token['start'],
                    'evidence_offset_end' => $token['start'] + mb_strlen($token['value']),
                    'gold_source' => null,
                ]);

                $created++;
            }
        }

        return ['created' => $created, 'cells' => $cells];
    }

    /**
     * @return array{created: int, pages: int, unreadable_pages: int, pages_without_new_candidates: int}
     */
    public function generate(int $limit = 200): array
    {
        $created = 0;
        $pages = 0;

        // Counted apart. A page rejected as unreviewable and a page already
        // harvested down to its last figure are opposite states, and reporting
        // both as "skipped" once made a corpus that had nothing left to give
        // look like a corpus the reader could not read.
        $unreadable = 0;
        $exhausted = 0;

        $candidates = ExtractionPage::query()
            ->with('run')
            ->whereNotNull('extracted_text')
            // Documents, not the websites they were found on. Discovery follows
            // every link a listing carries, so acquisition holds navigation
            // pages too — 58 of one publisher's 59 acquisitions are its own
            // menus and sector pages. Their text extracts perfectly well and
            // says "HOME | LINK | CONTACT US", and a reviewer asked to adjudicate
            // that is being asked to certify website furniture.
            ->whereHas('run.artifactVersion', fn ($query) => $query->whereNotIn('media_type', self::NON_DOCUMENT_MEDIA_TYPES))
            // One copy of each file. A duplicated document duplicates its
            // candidates, and a calibration set drawn from a pool where the
            // same document appears twice is no longer a sample of distinct
            // documents — which is the assumption the bound rests on.
            ->whereIn('extraction_run_id', ExtractionRun::query()
                ->whereIn('source_artifact_version_id', SourceArtifactVersion::query()->canonical()->select('id'))
                ->select('id'))
            // Reference lists are inputs, not corpus. The debarment CSV is
            // archived as a document and read as one, and it produced 901 review
            // candidates asking whether a World Bank record number was read
            // correctly — a question about somebody else's published register,
            // not about a Bangladeshi document this project certifies.
            ->whereNotIn(
                'extraction_run_id',
                ExtractionRun::query()
                    ->whereIn('source_artifact_version_id', ScreeningEntity::query()->select('source_artifact_version_id'))
                    ->select('id'),
            )
            // A page the recognizer would not vouch for is not worth a
            // reviewer's attention: they would be adjudicating text the system
            // has already declined to stand behind.
            ->where('extraction_path', '!=', SelectiveOcrService::ABSTAINED)
            ->where('character_count', '>', 50)
            // Identifiers first, then the pages themselves in batches. Loading
            // every candidate page at once meant holding every page's text in
            // memory: fine at 1,800 pages and fatal at 8,900, where it exhausted
            // 128 MB and killed the command outright.
            //
            // Sampling stays random. Taking the first N by id instead would
            // quietly bias a small sample toward the pages acquired earliest,
            // and the whole reason this draw is random is that a calibration set
            // must look like what the system actually reads.
            ->inRandomOrder()
            ->limit(max(1, $limit))
            ->pluck('id');

        foreach ($candidates->chunk(self::PAGES_PER_BATCH) as $batch) {
            foreach (ExtractionPage::query()->with('run')->findMany($batch->all()) as $page) {
                $pages++;
                $before = $created;

                // A page whose text layer is legacy-font mojibake is unreviewable.
                // Routing sends such pages to OCR now, but pages extracted before
                // that fix are still stored, and 88 percent of the first batch of
                // candidates were drawn from them. A reviewer would have marked
                // nine in ten incorrect and the bound would have described an
                // encoding bug rather than how well the system reads.
                if ($this->plausibility->isImplausible((string) $page->extracted_text)) {
                    $unreadable++;

                    continue;
                }

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
                        'normalized_value' => $token['kind'] === 'amount'
                            ? $this->normalisedAmount($token['value'])
                            : Str::of($token['value'])->replace(',', '')->trim()->value(),
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
                    $exhausted++;
                }
            }
        }

        return [
            'created' => $created,
            'pages' => $pages,
            'unreadable_pages' => $unreadable,
            'pages_without_new_candidates' => $exhausted,
        ];
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
        foreach (['date', 'reference', 'organisation', 'amount'] as $kind) {
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

                // A token ending in a separator is a fragment of a figure the
                // recognizer mangled: "US$23,¢8,80b" yielded "23,". Asking
                // whether that matches the page has no useful answer — the
                // characters are there, but the value is not a value.
                if ($kind === 'amount' && ! $this->wellFormedAmount($match[0])) {
                    continue;
                }

                $value = $match[0];

                if ($kind === 'amount') {
                    $value = $this->withoutResultMarker($value, $text, $match[1]);

                    // Dropping the marker moves the token one character along,
                    // and the offset is what the review screen locates the
                    // value by.
                    $start += mb_strlen($match[0]) - mb_strlen($value);
                }

                $claimed[] = [$start, $end];
                $found[] = ['kind' => $kind, 'value' => $value, 'start' => $start];
            }
        }

        return $found;
    }

    /**
     * A figure a reviewer can judge: digits, optional grouping, optional
     * decimals, and nothing dangling at either end.
     */
    /**
     * A bracketed calculation, whose result these reports write after it.
     *
     * `(৩,১৩,৫৪২-২,৮১,৪১৭) -৩২,১২৫` is a subtraction and its answer — the
     * arithmetic is exact — and the mark before the answer is an equals sign
     * the recognizer read as a hyphen.
     */
    private const RESULT_OF_CALCULATION = '/\((?:\s*[\d\x{09E6}-\x{09EF}][\d\x{09E6}-\x{09EF},.\x{0020}]*[-+\x{2212}])+\s*[\d\x{09E6}-\x{09EF}][\d\x{09E6}-\x{09EF},.\x{0020}]*\)\s*$/u';

    /**
     * Strip a leading minus that introduces a result rather than a sign.
     *
     * The bracket has to hold a calculation, and that condition is the whole
     * substance of this method. A first attempt tested only for a closing
     * bracket, which in a budget table is the shape of every ordinary row: a
     * percentage in parentheses sits before the next column's figure, so
     * `(১৭.৩৮) -১৯৩৭৮` is a genuine negative and stripping it invented money in
     * eight places before a reviewer's screenshot caught it.
     *
     * A minus anywhere else is left exactly as read. Reappropriation columns run
     * negative throughout this corpus — 385 of the 425 values that begin with
     * one — and the second read cannot arbitrate: it re-reads the same glyph
     * with the same engine family and agrees with itself, which is what ADR-019
     * means by a correlated failure.
     */
    private function withoutResultMarker(string $value, string $text, int $byteOffset): string
    {
        if ($value === '' || ! in_array(mb_substr($value, 0, 1), ['-', "\u{2212}"], true)) {
            return $value;
        }

        $before = substr($text, 0, $byteOffset);

        return preg_match(self::RESULT_OF_CALCULATION, $before) === 1
            ? mb_substr($value, 1)
            : $value;
    }

    private function wellFormedAmount(string $value): bool
    {
        return preg_match(
            '/^[\d\x{09E6}-\x{09EF}]{1,3}(?:,[\d\x{09E6}-\x{09EF}]{2,3})+(?:\.\d{1,2})?$|^[\d\x{09E6}-\x{09EF}]+\.\d{1,2}$|^[\d\x{09E6}-\x{09EF}]{5,}$/u',
            // Checked without its sign, so a well-formed figure is not rejected
            // for carrying one. The sign is kept on the value itself; only the
            // shape of the digits is in question here.
            $this->unsigned($value),
        ) === 1;
    }

    /**
     * The figure without whatever marks it as negative.
     */
    private function unsigned(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^\((.+)\)$/u', $value, $inner) === 1) {
            $value = $inner[1];
        }

        return ltrim($value, "-\u{2212} ");
    }

    /**
     * The figure as a number, with an accounting negative made explicit.
     *
     * The value keeps what the page shows; this is what any later analysis adds
     * up, and a bracketed figure summed as positive is a sign error in the
     * arithmetic rather than in the reading.
     */
    private function normalisedAmount(string $value): ?string
    {
        // A comma that does not group thousands is most likely a decimal point
        // the recognizer misread, and stripping it turns 400.00 into 80,000. No
        // number is better than one that is wrong by two orders of magnitude,
        // so the figure is queued for judgement carrying no normalised form.
        if (! $this->grouping->groupsCorrectly($value)) {
            return null;
        }

        $digits = str_replace(',', '', $this->unsigned($value));
        $negative = preg_match('/^\(|^[-\x{2212}]/u', trim($value)) === 1;

        return ($negative ? '-' : '').$digits;
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
