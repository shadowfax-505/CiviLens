<?php

namespace App\Services\PublicPortal;

use App\Models\PublicationDocument;
use App\Models\PublicationFigure;
use App\Models\PublicationNotice;
use App\Models\PublicationSnapshot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Reads the published snapshot, and nothing else.
 *
 * Every other public service reads the domain tables, which hold demonstration
 * rows. This one reads only what was certified and published, so a page built
 * on it cannot accidentally show a figure the system declined to stand behind
 * or a value that changed after someone cited it.
 */
class PublicEvidenceService
{
    public function currentSnapshot(): ?PublicationSnapshot
    {
        return PublicationSnapshot::current();
    }

    /**
     * Documents in the published snapshot.
     *
     * Defaults to the ones that are documents rather than archived web pages,
     * because a reader looking for audit reports should not have to page
     * through four hundred archived index pages to find nine.
     *
     * @param  array{q?: string|null, publisher?: string|null, kind?: string|null}  $filters
     * @return LengthAwarePaginator<int, PublicationDocument>
     */
    public function documents(PublicationSnapshot $snapshot, array $filters = []): LengthAwarePaginator
    {
        $kind = $filters['kind'] ?? 'documents';

        return $snapshot->documents()
            ->when($kind === 'documents', fn ($query) => $query->where('is_document', true))
            ->when($kind === 'archived', fn ($query) => $query->where('is_document', false))
            ->when(
                ($filters['publisher'] ?? null) !== null && $filters['publisher'] !== '',
                fn ($query) => $query->where('publisher_slug', $filters['publisher']),
            )
            ->when(
                ($filters['q'] ?? null) !== null && $filters['q'] !== '',
                fn ($query) => $query->where(function ($inner) use ($filters): void {
                    $inner->where('original_filename', 'like', '%'.$filters['q'].'%')
                        ->orWhere('source_url', 'like', '%'.$filters['q'].'%');
                }),
            )
            ->orderByDesc('figure_count')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * A document's certified figures, in page order.
     *
     * @return Collection<int, PublicationFigure>
     */
    public function figuresFor(PublicationDocument $document): Collection
    {
        return $document->figures()
            ->orderBy('page_number')
            ->orderBy('id')
            ->get();
    }

    /**
     * What the snapshot is claiming, in the terms the claim was made in.
     *
     * ADR-018: the level a group was certified at travels with it everywhere
     * it is reported, and a threshold borrowed from a wider pool is not a
     * conditional claim about the group inside it. Both are surfaced here so a
     * page cannot show a bare count.
     *
     * @return list<array{group: string, script: string, alpha: float|null, basis: string|null, conditional: bool, figures: int}>
     */
    public function certificationSummary(PublicationSnapshot $snapshot): array
    {
        $rows = $snapshot->figures()
            ->select(['publisher_group', 'script_class', 'certified_alpha', 'decision_basis'])
            ->selectRaw('count(*) as aggregate')
            ->groupBy('publisher_group', 'script_class', 'certified_alpha', 'decision_basis')
            ->orderBy('publisher_group')
            ->orderBy('script_class')
            ->get();

        return array_values($rows
            ->map(fn (PublicationFigure $row): array => [
                'group' => (string) $row->publisher_group,
                'script' => (string) $row->script_class,
                'alpha' => $row->certified_alpha,
                'basis' => $row->decision_basis,
                'conditional' => $row->isConditional(),
                'figures' => (int) $row->getAttribute('aggregate'),
            ])
            ->all());
    }

    /**
     * @return list<string>
     */
    public function publishers(PublicationSnapshot $snapshot): array
    {
        return array_values($snapshot->documents()
            ->select('publisher_slug')
            ->distinct()
            ->orderBy('publisher_slug')
            ->pluck('publisher_slug')
            ->map(fn (string $slug): string => $slug)
            ->all());
    }

    /**
     * @param  array{q?: string|null}  $filters
     * @return LengthAwarePaginator<int, PublicationNotice>
     */
    public function notices(PublicationSnapshot $snapshot, array $filters = []): LengthAwarePaginator
    {
        return $snapshot->notices()
            ->when(
                ($filters['q'] ?? null) !== null && $filters['q'] !== '',
                fn ($query) => $query->where('reference_number', 'like', '%'.$filters['q'].'%'),
            )
            ->orderByDesc('published_on')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();
    }
}
