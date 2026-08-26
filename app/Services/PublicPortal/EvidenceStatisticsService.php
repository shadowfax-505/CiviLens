<?php

namespace App\Services\PublicPortal;

use App\Models\ExtractionField;
use App\Models\PublicationSnapshot;
use App\Services\Statistics\ClopperPearson;
use Illuminate\Support\Facades\DB;

/**
 * What can honestly be counted about the published corpus.
 *
 * Deliberately no totals. The figures published here sit beside one another as
 * `১৯৪০.৮৩`, `233.28` and `১৩২৩৮৪` — crore, taka and percentages, on pages that
 * declare their unit in a heading this system does not read. Adding them would
 * produce a number with no referent, and it would be the number every reader
 * quoted. A count of figures is a fact about this corpus; a sum of them would
 * be an assertion about Bangladesh's public spending that nothing here
 * supports.
 *
 * Everything counted is reported beside what was withheld. "14,277 certified
 * figures" invites a reader to treat that as the contents of these documents;
 * it is the part the system was willing to stand behind, and the share it was
 * not belongs in the same sentence.
 */
class EvidenceStatisticsService
{
    public function __construct(private readonly ClopperPearson $clopperPearson) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $snapshot = PublicationSnapshot::current();

        if (! $snapshot instanceof PublicationSnapshot) {
            return ['snapshot' => null, 'published' => 0, 'withheld' => 0, 'publishers' => []];
        }

        $published = (int) $snapshot->figure_count;

        // Withheld is measured live rather than from the snapshot, because a
        // snapshot holds only what was published. The share is the point of the
        // whole report and it cannot be computed from the published side alone.
        $withheld = ExtractionField::query()->where('decision', 'deferred')->count();
        $judgedWrong = ExtractionField::query()->where('is_correct', false)->count();
        $judged = ExtractionField::query()->whereNotNull('gold_source')->count();

        return [
            'snapshot' => $snapshot->label,
            'published' => $published,
            'withheld' => $withheld,
            'share_withheld' => $published + $withheld > 0
                ? round($withheld / ($published + $withheld), 4)
                : null,
            'by_kind' => $this->byKind($snapshot),
            'publishers' => $this->byPublisher($snapshot),
            'judged_by_a_person' => $judged,
            'judged_wrong' => $judgedWrong,
            // The measured error rate with its interval, so a reader cannot take
            // the rate for a precise quantity. M5 established that at this
            // sample the interval reaches well past the level being claimed.
            'error_rate_among_judged' => $judged > 0
                ? [
                    'rate' => round($judgedWrong / $judged, 4),
                    'interval' => $this->clopperPearson->interval($judgedWrong, $judged),
                ]
                : null,
            'why_there_are_no_totals' => 'These figures are counted, never added. They sit beside one '
                .'another in crore, in taka and as percentages, on pages that declare the unit in a '
                .'heading this system does not read. A sum of them would have no referent, and it '
                .'would be the number everyone quoted.',
        ];
    }

    /**
     * @return array<string, int>
     */
    private function byKind(PublicationSnapshot $snapshot): array
    {
        return DB::table('publication_figures')
            ->where('publication_snapshot_id', $snapshot->getKey())
            ->selectRaw('field_kind, count(*) as aggregate')
            ->groupBy('field_kind')
            ->orderByDesc('aggregate')
            ->pluck('aggregate', 'field_kind')
            ->map(fn (int|string $value): int => (int) $value)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function byPublisher(PublicationSnapshot $snapshot): array
    {
        $documents = DB::table('publication_documents')
            ->where('publication_snapshot_id', $snapshot->getKey())
            ->selectRaw('publisher_slug, count(*) as documents, sum(figure_count) as figures, sum(case when is_document = 1 then 1 else 0 end) as real_documents')
            ->groupBy('publisher_slug')
            ->orderByDesc('figures')
            ->get();

        $withheld = ExtractionField::query()
            ->where('decision', 'deferred')
            ->selectRaw('publisher_group, count(*) as aggregate')
            ->groupBy('publisher_group')
            ->pluck('aggregate', 'publisher_group');

        return array_values($documents->map(function (object $row) use ($withheld): array {
            $figures = (int) $row->figures;
            $deferred = (int) ($withheld[$row->publisher_slug] ?? 0);

            return [
                'publisher' => (string) $row->publisher_slug,
                'documents' => (int) $row->real_documents,
                'archived_items' => (int) $row->documents,
                'figures_published' => $figures,
                'figures_withheld' => $deferred,
                'share_withheld' => $figures + $deferred > 0
                    ? round($deferred / ($figures + $deferred), 4)
                    : null,
            ];
        })->all());
    }
}
