<?php

namespace App\Services\PublicPortal;

use App\Models\ExtractionPage;
use App\Models\PublicationDocument;
use App\Models\PublicationFigure;
use App\Models\PublicationSnapshot;
use App\Support\BengaliNumerals;
use Illuminate\Support\Collection;

/**
 * Search over what has been published, in either alphabet.
 *
 * A reader looking at a scanned page sees `৫,১৪,০৫১` and types it. The page it
 * came from may store that figure as `514,051`, because legacy fonts in this
 * corpus write Bengali numerals as the Latin bytes that render them — so a
 * literal search finds nothing and the reader concludes the document is not
 * here. Every query is therefore matched in both alphabets.
 *
 * Grouping is ignored for figures. `৫,১৪,০৫১` and `৫১৪,০৫১` are one amount
 * separated two ways, and a reader typing either means the same number. The
 * separator still matters to *certification* — a misplaced one is a hundredfold
 * error — but not to finding the thing.
 *
 * Only the published snapshot is searched. Search over deferred values would
 * hand a reader figures the system has declined to stand behind, with none of
 * the context that says so.
 */
class EvidenceSearchService
{
    public function __construct(private readonly BengaliNumerals $numerals) {}

    /**
     * @return array{query: string, snapshot: string|null, figures: Collection<int, PublicationFigure>, pages: list<array{document: PublicationDocument, page_number: int, excerpt: string}>, searched_as: list<string>}
     */
    public function search(string $query, int $limit = 30): array
    {
        $query = trim($query);
        $snapshot = PublicationSnapshot::current();

        $empty = [
            'query' => $query,
            'snapshot' => $snapshot?->label,
            'figures' => new Collection,
            'pages' => [],
            'searched_as' => [],
        ];

        if ($query === '' || ! $snapshot instanceof PublicationSnapshot) {
            return $empty;
        }

        $latin = $this->numerals->toLatin($query);
        $bengali = $this->numerals->toBengali($query);
        $digits = $this->numerals->digitsOnly($query);

        return [
            'query' => $query,
            'snapshot' => $snapshot->label,
            'figures' => $this->figures($snapshot, $query, $latin, $bengali, $digits, $limit),
            'pages' => $this->pages($snapshot, $latin, $bengali, $limit),
            // Shown to the reader, because a search that silently rewrote the
            // query and found nothing is indistinguishable from one that looked
            // for what was typed.
            'searched_as' => array_values(array_unique(array_filter([$query, $latin, $bengali]))),
        ];
    }

    /**
     * @return Collection<int, PublicationFigure>
     */
    private function figures(
        PublicationSnapshot $snapshot,
        string $query,
        string $latin,
        string $bengali,
        string $digits,
        int $limit,
    ): Collection {
        $matches = PublicationFigure::query()
            ->where('publication_snapshot_id', $snapshot->getKey())
            ->where(function ($builder) use ($query, $latin, $bengali): void {
                foreach (array_unique([$query, $latin, $bengali]) as $form) {
                    $builder->orWhere('extracted_value', 'like', '%'.$form.'%')
                        ->orWhere('normalized_value', 'like', '%'.$form.'%');
                }
            })
            ->with('document')
            ->limit($limit)
            ->get();

        if ($digits === '' || $matches->count() >= $limit) {
            return $matches;
        }

        // A figure the reader typed with different separators. Compared on
        // digits alone, which is what makes ৫,১৪,০৫১ find ৫১৪,০৫১.
        $byDigits = PublicationFigure::query()
            ->where('publication_snapshot_id', $snapshot->getKey())
            ->whereNotIn('id', $matches->modelKeys())
            ->with('document')
            ->get()
            ->filter(fn (PublicationFigure $figure): bool => $this->numerals->digitsOnly((string) $figure->extracted_value) === $digits)
            ->take($limit - $matches->count());

        return $matches->concat($byDigits)->values();
    }

    /**
     * Pages of published documents whose text carries the query.
     *
     * @return list<array{document: PublicationDocument, page_number: int, excerpt: string}>
     */
    private function pages(PublicationSnapshot $snapshot, string $latin, string $bengali, int $limit): array
    {
        $documents = PublicationDocument::query()
            ->where('publication_snapshot_id', $snapshot->getKey())
            ->where('is_document', true)
            ->get()
            ->keyBy('source_artifact_version_id');

        if ($documents->isEmpty()) {
            return [];
        }

        $rows = ExtractionPage::query()
            ->whereHas('run', fn ($builder) => $builder->whereIn('source_artifact_version_id', $documents->keys()))
            ->where(function ($builder) use ($latin, $bengali): void {
                foreach (array_unique([$latin, $bengali]) as $form) {
                    $builder->orWhere('extracted_text', 'like', '%'.$form.'%');
                }
            })
            ->with('run')
            ->limit($limit)
            ->get()
            ->map(function (ExtractionPage $page) use ($documents, $latin, $bengali): ?array {
                $document = $documents->get($page->run?->source_artifact_version_id);

                if (! $document instanceof PublicationDocument) {
                    return null;
                }

                return [
                    'document' => $document,
                    'page_number' => (int) $page->page_number,
                    'excerpt' => $this->excerpt((string) $page->extracted_text, [$latin, $bengali]),
                ];
            })
            ->filter()
            ->values();

        return array_values($rows->all());
    }

    /**
     * @param  list<string>  $forms
     */
    private function excerpt(string $text, array $forms, int $window = 90): string
    {
        foreach ($forms as $form) {
            if ($form === '') {
                continue;
            }

            $position = mb_stripos($text, $form);

            if ($position === false) {
                continue;
            }

            $start = max(0, $position - $window);

            return trim((string) preg_replace('/\s+/u', ' ', mb_substr($text, $start, $window * 2 + mb_strlen($form))));
        }

        return trim((string) preg_replace('/\s+/u', ' ', mb_substr($text, 0, $window)));
    }
}
