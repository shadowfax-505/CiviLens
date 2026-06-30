<?php

namespace App\Services\Search;

use App\Contracts\Search\Searchable;
use App\Models\Agency;
use App\Models\Budget;
use App\Models\Document;
use App\Models\Documentable;
use App\Models\Project;
use App\Models\SearchIndex;
use App\Models\Tender;
use App\Models\User;
use App\Support\Search\SearchResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class KnowledgeGraphService
{
    /**
     * @return array{entity: SearchResult, relationships: array<string, array<int, SearchResult>>}
     */
    public function for(Searchable $entity, User $user): array
    {
        return [
            'entity' => $this->resultFor($entity),
            'relationships' => $this->relationships($entity, $user),
        ];
    }

    private function resultFor(Searchable $entity): SearchResult
    {
        if ($entity instanceof Model) {
            $index = SearchIndex::query()
                ->where('searchable_type', $entity::class)
                ->where('searchable_id', $entity->getKey())
                ->first();

            if ($index instanceof SearchIndex) {
                return SearchResult::fromIndex($index);
            }
        }

        return new SearchResult(
            id: 0,
            module: $entity->searchModule(),
            title: $entity->searchTitle(),
            description: $entity->searchDescription(),
            url: $entity->searchUrl(),
            searchableType: $entity instanceof Model ? $entity::class : $entity::class,
            searchableId: $entity instanceof Model ? (int) $entity->getKey() : 0,
            score: 0,
            visibility: $entity->searchVisibility(),
            status: $entity->searchStatus(),
            metadata: $entity->searchMetadata(),
        );
    }

    /**
     * @return array<string, array<int, SearchResult>>
     */
    private function relationships(Searchable $entity, User $user): array
    {
        if ($entity instanceof Project) {
            return [
                'agency' => $this->results(collect([$entity->agency])->filter(), $user),
                'budgets' => $this->results($entity->budgets()->get(), $user),
                'procurement' => $this->results($entity->tenders()->get(), $user),
                'documents' => $this->results($this->documentsFor($entity), $user),
            ];
        }

        if ($entity instanceof Budget) {
            return [
                'project' => $this->results(collect([$entity->project])->filter(), $user),
                'procurement' => $this->results($entity->tenders()->get(), $user),
                'documents' => $this->results($this->documentsFor($entity), $user),
            ];
        }

        if ($entity instanceof Tender) {
            return [
                'project' => $this->results(collect([$entity->project])->filter(), $user),
                'budget' => $this->results(collect([$entity->budget])->filter(), $user),
                'agency' => $this->results(collect([$entity->agency])->filter(), $user),
                'documents' => $this->results($this->documentsFor($entity), $user),
            ];
        }

        if ($entity instanceof Agency) {
            return [
                'projects' => $this->results($entity->projects()->get(), $user),
                'procurement' => $this->results($entity->tenders()->get(), $user),
                'documents' => $this->results($this->documentsFor($entity), $user),
            ];
        }

        if ($entity instanceof Document) {
            return [
                'attached_to' => $this->results($this->documentTargets($entity), $user),
            ];
        }

        return [];
    }

    /**
     * @param  Collection<int, mixed>  $records
     * @return array<int, SearchResult>
     */
    private function results(Collection $records, User $user): array
    {
        return $records
            ->filter(fn (mixed $record): bool => $record instanceof Searchable && $record instanceof Model && $this->canView($record, $user))
            ->map(fn (Searchable $record): SearchResult => $this->resultFor($record))
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Document>
     */
    private function documentsFor(Model $entity): Collection
    {
        return Documentable::query()
            ->where('documentable_type', $entity::class)
            ->where('documentable_id', $entity->getKey())
            ->with('document')
            ->get()
            ->pluck('document')
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, Model>
     */
    private function documentTargets(Document $document): Collection
    {
        return $document->documentables()
            ->get()
            ->map(fn (Documentable $documentable): ?Model => $documentable->target())
            ->filter()
            ->values();
    }

    private function canView(Model $record, User $user): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'))
            || Gate::forUser($user)->allows('view', $record)
            || Gate::forUser($user)->allows('viewAny', $record::class);
    }
}
