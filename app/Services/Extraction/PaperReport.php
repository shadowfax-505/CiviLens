<?php

namespace App\Services\Extraction;

use App\Models\DiscoveredResource;
use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\ExtractionTableCell;
use App\Models\SourceArtifactVersion;
use App\Models\SourceEndpoint;
use App\Models\SourcePublisher;
use App\Models\TenderObservation;
use Illuminate\Support\Collection;

/**
 * Every number a write-up needs, from one command against a live database.
 *
 * Figures quoted from a chat log or a screenshot cannot be checked and go stale
 * silently. This regenerates them, including the ones that are unflattering: the
 * share of pages the recognizer would not vouch for, the share of values that
 * cannot be located, and the fact that document extraction rests on a single
 * publisher.
 */
class PaperReport
{
    /** What a publisher publishes as a document, rather than the site around it. */
    private const DOCUMENT_MEDIA_TYPES = [
        'application/pdf',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.oasis.opendocument.spreadsheet',
        'text/csv',
    ];

    public function __construct(
        private readonly ScoreDiscriminationReport $discrimination,
        private readonly CalibrationReport $calibration,
        private readonly AmountGrouping $grouping,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(float $alpha = 0.05): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'alpha' => $alpha,
            'corpus' => $this->corpus(),
            'extraction' => $this->extraction(),
            'tables' => $this->tables(),
            'labels' => $this->labels(),
            'calibration' => $this->calibration->build($alpha),
            'score' => $this->score(),
            'failure_modes' => $this->failureModes(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function corpus(): array
    {
        return [
            'publishers_registered' => SourcePublisher::query()->count(),
            'endpoints' => SourceEndpoint::query()->count(),
            'resources_discovered' => DiscoveredResource::query()->count(),
            // Split, because discovery follows every link a listing carries and
            // acquisition therefore holds each publisher's own menus beside its
            // reports. Counting them together reported 413 documents for a
            // corpus that holds 11, which is the kind of figure that reaches a
            // write-up and is never checked again.
            'documents_acquired' => $this->artifactsOfKind(true),
            'web_pages_and_records_archived' => $this->artifactsOfKind(false),
            // Structured rows captured from a listing that publishes records
            // rather than files. A second publisher's data, but not a second
            // publisher's documents, and the difference matters to any claim
            // about extraction.
            'tender_observations' => TenderObservation::query()->count(),
            // Sources fetched against a publisher's stated robots directive, on
            // a recorded operator decision. Anything derived from them carries
            // that provenance, and a write-up that omitted it would be hiding
            // the one thing a reader might object to.
            'fetched_against_robots' => $this->overriddenEndpoints(),
            'documents_by_publisher' => $this->documentsByPublisher(),
            'archived_by_publisher' => $this->documentsByPublisher(documentsOnly: false),
        ];
    }

    /**
     * Built by hand rather than mapped: the publisher behind a resource is two
     * relations away and either of them can be missing, which a chained accessor
     * hides and a count then attributes to the wrong publisher.
     *
     * @return array<string, int>
     */
    private function documentsByPublisher(bool $documentsOnly = true): array
    {
        $counts = [];

        $resources = DiscoveredResource::query()
            ->where('status', 'acquired')
            ->with(['endpoint.publisher', 'artifactVersions'])
            ->get()
            ->filter(fn (DiscoveredResource $resource): bool => $this->isDocument($resource) === $documentsOnly);

        foreach ($resources as $resource) {
            $endpoint = $resource->endpoint;
            $publisher = $endpoint instanceof SourceEndpoint ? $endpoint->publisher : null;
            $slug = $publisher instanceof SourcePublisher ? $publisher->slug : 'unattributed';

            $counts[$slug] = ($counts[$slug] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Sources fetched against a publisher's stated robots directive.
     *
     * Built by hand: the publisher is a relation away and can be missing, which
     * a chained accessor hides.
     *
     * @return list<array{publisher: string, endpoint: string, reason: string}>
     */
    private function overriddenEndpoints(): array
    {
        $overridden = [];

        foreach (SourceEndpoint::query()->whereNotNull('robots_override_reason')->with('publisher')->get() as $endpoint) {
            $publisher = $endpoint->publisher;

            $overridden[] = [
                'publisher' => $publisher instanceof SourcePublisher ? $publisher->slug : 'unattributed',
                'endpoint' => (string) $endpoint->name,
                'reason' => (string) $endpoint->robots_override_reason,
            ];
        }

        return $overridden;
    }

    /**
     * A document is something the publisher published as one. HTML is the site
     * around it and JSON is an archived record feed; both belong in the corpus
     * and neither is a document.
     */
    private function isDocument(DiscoveredResource $resource): bool
    {
        $version = $resource->artifactVersions->first();

        return $version instanceof SourceArtifactVersion
            && in_array((string) $version->media_type, self::DOCUMENT_MEDIA_TYPES, true);
    }

    private function artifactsOfKind(bool $documents): int
    {
        $query = SourceArtifactVersion::query();

        return $documents
            ? $query->whereIn('media_type', self::DOCUMENT_MEDIA_TYPES)->count()
            : $query->whereNotIn('media_type', self::DOCUMENT_MEDIA_TYPES)->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function extraction(): array
    {
        $pages = ExtractionPage::query()->count();
        $byPath = ExtractionPage::query()->get(['extraction_path'])->countBy('extraction_path')->all();

        return [
            'pages' => $pages,
            'by_path' => $byPath,
            // Abstention is the figure a summary is most tempted to omit: these
            // are pages the recognizer attempted and declined to stand behind.
            'abstained_share' => $pages === 0
                ? null
                : round(((int) ($byPath[SelectiveOcrService::ABSTAINED] ?? 0)) / $pages, 4),
            'pages_with_word_geometry' => ExtractionPage::query()->whereNotNull('recognized_words')->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tables(): array
    {
        $pagesWithTables = ExtractionTableCell::query()->distinct()->count('extraction_page_id');
        $cells = ExtractionTableCell::query()->count();

        return [
            'pages_with_tables' => $pagesWithTables,
            'cells' => $cells,
            'cells_per_page' => $pagesWithTables === 0 ? null : round($cells / $pagesWithTables, 1),
            'fields_carrying_a_row' => ExtractionField::query()->whereNotNull('extraction_table_cell_id')->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function labels(): array
    {
        $judged = ExtractionField::query()->whereNotNull('is_correct');

        return [
            'adjudicated' => (clone $judged)->count(),
            'incorrect' => (clone $judged)->where('is_correct', false)->count(),
            // Recorded as looked at and left undecided. Counted, because a
            // hesitation coerced into a verdict is a guess inside the
            // calibration set.
            'undecided' => ExtractionField::query()->where('gold_source', 'reviewer-unsure')->count(),
            'by_group' => (clone $judged)->get(['publisher_group', 'script_class'])
                ->countBy(fn (ExtractionField $f): string => $f->publisher_group.'|'.$f->script_class)
                ->all(),
            'decisions' => ExtractionField::query()->whereNull('gold_source')
                ->get(['decision'])->countBy('decision')->all(),
        ];
    }

    /**
     * The score in use against the one it replaced, recomputed side by side.
     *
     * The page-confidence baseline is derivable from data still on the page, so
     * the comparison regenerates rather than being quoted from a note.
     *
     * @return array<string, mixed>
     */
    private function score(): array
    {
        $current = $this->discrimination->build();
        $labelled = ExtractionField::query()->calibratable()->with('page')->get();

        $baseline = [];

        foreach ($labelled as $field) {
            $page = $field->page;

            $baseline[] = [
                'score' => $page instanceof ExtractionPage && $page->confidence !== null
                    ? round(1 - ((float) $page->confidence / 100), 8)
                    : 1.0,
                'wrong' => $field->is_correct === false,
            ];
        }

        return [
            'second_read' => $current,
            'page_confidence_baseline' => ['auc' => $this->auc($baseline)],
            'acceptance_at_alpha' => $this->acceptanceCurve($labelled),
        ];
    }

    /**
     * How much each score can accept at a given risk level, on the same fields.
     *
     * At a loose alpha both look alike, because a corpus whose error rate is
     * already inside the bound needs no threshold at all. The difference is what
     * a score buys when the claim is tightened.
     *
     * @param  Collection<int, ExtractionField>  $labelled
     * @return array<string, array<string, float|null>>
     */
    private function acceptanceCurve(Collection $labelled): array
    {
        $rows = $labelled->map(function (ExtractionField $field): array {
            $page = $field->page;

            return [
                'new' => (float) $field->nonconformity_score,
                'old' => $page instanceof ExtractionPage && $page->confidence !== null
                    ? round(1 - ((float) $page->confidence / 100), 8)
                    : 1.0,
                'wrong' => $field->is_correct === false,
            ];
        })->values();

        $curve = [];

        foreach ([0.05, 0.03, 0.02] as $alpha) {
            $curve[(string) $alpha] = [
                'page_confidence' => $this->acceptedShare($rows, 'old', $alpha),
                'second_read' => $this->acceptedShare($rows, 'new', $alpha),
            ];
        }

        return $curve;
    }

    /**
     * @param  Collection<int, array{new: float, old: float, wrong: bool}>  $rows
     */
    private function acceptedShare(Collection $rows, string $key, float $alpha): ?float
    {
        $count = $rows->count();

        if ($count === 0) {
            return null;
        }

        $sorted = $rows->sortBy($key)->values();
        $errors = 0;
        $accepted = 0;

        foreach ($sorted as $index => $row) {
            if ($row['wrong']) {
                $errors++;
            }

            // Only at the end of a run of equal scores: accepting part of a tie
            // is not something a threshold can express.
            $next = $sorted->get($index + 1);

            if (is_array($next) && $next[$key] === $row[$key]) {
                continue;
            }

            if (($errors + 1) / ($count + 1) <= $alpha) {
                $accepted = $index + 1;
            }
        }

        return round($accepted / $count, 4);
    }

    /**
     * @param  list<array{score: float, wrong: bool}>  $rows
     */
    private function auc(array $rows): ?float
    {
        $wrong = [];
        $right = [];

        foreach ($rows as $row) {
            $row['wrong'] ? $wrong[] = $row['score'] : $right[] = $row['score'];
        }

        if ($wrong === [] || $right === []) {
            return null;
        }

        $wins = 0.0;

        foreach ($wrong as $bad) {
            foreach ($right as $good) {
                $wins += $bad > $good ? 1.0 : ($bad === $good ? 0.5 : 0.0);
            }
        }

        return round($wins / (count($wrong) * count($right)), 4);
    }

    /**
     * Failure modes found by reviewers, with the rate each occurs at.
     *
     * @return array<string, mixed>
     */
    private function failureModes(): array
    {
        $amounts = ExtractionField::query()->where('field_key', 'amount')->get(['extracted_value', 'script_class']);
        $withComma = $amounts->filter(fn (ExtractionField $f): bool => str_contains((string) $f->extracted_value, ','));

        return [
            'separator_suspect' => [
                'comma_bearing_amounts' => $withComma->count(),
                'grouped_as_no_convention_allows' => $withComma
                    ->reject(fn (ExtractionField $f): bool => $this->grouping->groupsCorrectly((string) $f->extracted_value))
                    ->count(),
            ],
            'signed_amounts_kept' => $amounts
                ->filter(fn (ExtractionField $f): bool => (bool) preg_match('/^[(\-\x{2212}]/u', (string) $f->extracted_value))
                ->count(),
            // A value the locator cannot find is deferred and stays out of
            // calibration; the share of them is the ceiling on how much of the
            // corpus can be certified at all.
            'values_not_locatable' => ExtractionField::query()->where('score_basis', 'unlocatable')->count(),
            'values_scored' => ExtractionField::query()->where('score_basis', 'second-read')->count(),
        ];
    }
}
