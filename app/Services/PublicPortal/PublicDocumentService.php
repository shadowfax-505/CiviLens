<?php

namespace App\Services\PublicPortal;

use App\Models\Document;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class PublicDocumentService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Document>
     */
    public function listing(array $filters = []): LengthAwarePaginator
    {
        return $this->publicQuery()
            ->when($filters['q'] ?? null, function ($builder, string $query): void {
                $builder->where(function ($builder) use ($query): void {
                    $builder->where('title', 'like', '%'.$query.'%')
                        ->orWhere('description', 'like', '%'.$query.'%')
                        ->orWhere('original_filename', 'like', '%'.$query.'%');
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();
    }

    public function findPublic(Document $document): Document
    {
        $document->load(['visibility', 'status']);

        abort_unless(app(PublicVisibilityService::class)->documentIsPublic($document), 403);

        return $document;
    }

    /**
     * @return Collection<int, Document>
     */
    public function forProject(Project $project): Collection
    {
        return $this->publicQuery()
            ->whereHas('documentables', fn ($builder) => $builder
                ->where('documentable_type', Project::class)
                ->where('documentable_id', $project->id))
            ->latest()
            ->limit(6)
            ->get();
    }

    /**
     * @return Builder<Document>
     */
    private function publicQuery(): Builder
    {
        return Document::query()
            ->with(['visibility', 'status', 'type', 'category'])
            ->whereNull('archived_at')
            ->whereHas('visibility', fn ($builder) => $builder->where('slug', 'public'))
            ->whereHas('status', fn ($builder) => $builder->where('slug', '!=', 'archived'));
    }
}
