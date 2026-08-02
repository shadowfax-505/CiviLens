<?php

namespace App\Services\PublicPortal;

use App\Models\Document;
use App\Models\Project;
use App\Models\Tender;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type TimelineItem array{type: string, title: string, summary: string, publisher: string, geography: string|null, source_class: string, indexed_at: CarbonInterface, url: string}
 */
class PublicRecentlyIndexedService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, TimelineItem>
     */
    public function timeline(array $filters = [], int $limit = 9): Collection
    {
        if (($filters['source_class'] ?? null) === 'non-government') {
            return collect();
        }

        $types = $filters['timeline_type'] ?? null;
        $items = [];

        if ($types === null || $types === 'project') {
            $items = array_merge($items, $this->projects($filters, $limit));
        }

        if ($types === null || $types === 'procurement') {
            $items = array_merge($items, $this->tenders($filters, $limit));
        }

        if ($types === null || $types === 'document') {
            $items = array_merge($items, $this->documents($filters, $limit));
        }

        return collect($items)
            ->sortByDesc(fn (array $item): string => $item['indexed_at']->format(DATE_ATOM))
            ->take($limit)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<TimelineItem>
     */
    private function projects(array $filters, int $limit): array
    {
        return array_values(Project::query()
            ->with(['agency:id,name', 'district:id,name'])
            ->where('is_public', true)
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->when($filters['district_id'] ?? null, fn ($query, int $districtId) => $query->where('district_id', $districtId))
            ->when($filters['publisher_id'] ?? null, fn ($query, int $agencyId) => $query->where('agency_id', $agencyId))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->latest()
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(/** @return TimelineItem */ fn (Project $project): array => [
                'type' => 'Project',
                'title' => $project->name,
                'summary' => str($project->description)->limit(170)->toString(),
                'publisher' => $this->relatedString($project, 'agency.name') ?? 'Public project registry',
                'geography' => $this->relatedString($project, 'district.name'),
                'source_class' => 'government',
                'indexed_at' => $this->timestamp($project),
                'url' => route('public.projects.show', $project),
            ])->all());
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<TimelineItem>
     */
    private function tenders(array $filters, int $limit): array
    {
        return array_values(Tender::query()
            ->with(['agency:id,name', 'project.district:id,name'])
            ->where('is_public', true)
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->when($filters['district_id'] ?? null, fn ($query, int $districtId) => $query->whereHas('project', fn ($query) => $query->where('district_id', $districtId)))
            ->when($filters['publisher_id'] ?? null, fn ($query, int $agencyId) => $query->where('agency_id', $agencyId))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate(DB::raw('COALESCE(published_at, created_at)'), '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate(DB::raw('COALESCE(published_at, created_at)'), '<=', $date))
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(/** @return TimelineItem */ fn (Tender $tender): array => [
                'type' => 'Procurement',
                'title' => $tender->title,
                'summary' => str($tender->description)->limit(170)->toString(),
                'publisher' => $this->relatedString($tender, 'agency.name') ?? 'Public procurement registry',
                'geography' => $this->relatedString($tender, 'project.district.name'),
                'source_class' => 'government',
                'indexed_at' => $this->timestamp($tender, 'published_at'),
                'url' => route('public.procurement.show', $tender),
            ])->all());
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<TimelineItem>
     */
    private function documents(array $filters, int $limit): array
    {
        if (($filters['district_id'] ?? null) !== null || ($filters['publisher_id'] ?? null) !== null) {
            return [];
        }

        return array_values(Document::query()
            ->with(['type:id,name', 'category:id,name'])
            ->whereNull('archived_at')
            ->whereHas('visibility', fn ($query) => $query->where('slug', 'public'))
            ->whereHas('status', fn ($query) => $query->where('slug', '!=', 'archived'))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->latest()
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(/** @return TimelineItem */ fn (Document $document): array => [
                'type' => $this->relatedString($document, 'type.name') ?? 'Document',
                'title' => $document->title,
                'summary' => str($document->description)->limit(170)->toString(),
                'publisher' => 'CivicLens public document registry',
                'geography' => null,
                'source_class' => 'government',
                'indexed_at' => $this->timestamp($document),
                'url' => route('public.documents.download', $document),
            ])->all());
    }

    private function relatedString(Project|Tender|Document $record, string $path): ?string
    {
        $value = data_get($record, $path);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function timestamp(Model $record, string $preferred = 'created_at'): CarbonInterface
    {
        $value = $record->getAttribute($preferred) ?? $record->getAttribute('created_at');

        if ($value instanceof CarbonInterface) {
            return $value;
        }

        return CarbonImmutable::parse(is_string($value) ? $value : 'now');
    }
}
