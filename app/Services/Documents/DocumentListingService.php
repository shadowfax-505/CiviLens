<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DocumentListingService
{
    /**
     * @return LengthAwarePaginator<int, Document>
     */
    public function paginate(Request $request, bool $archived = false): LengthAwarePaginator
    {
        $sort = in_array($request->query('sort'), ['title', 'original_filename', 'file_size', 'created_at', 'version_number'], true)
            ? $request->query('sort')
            : 'created_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        return Document::query()
            ->with(['type', 'category', 'status', 'visibility', 'owner', 'uploader', 'tags', 'documentables'])
            ->when($archived, fn (Builder $query) => $query->whereNotNull('archived_at'), fn (Builder $query) => $query->whereNull('archived_at'))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('original_filename', 'like', "%{$search}%")
                        ->orWhereHas('tags', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('document_type_id'), fn (Builder $query) => $query->where('document_type_id', $request->query('document_type_id')))
            ->when($request->filled('document_category_id'), fn (Builder $query) => $query->where('document_category_id', $request->query('document_category_id')))
            ->when($request->filled('document_status_id'), fn (Builder $query) => $query->where('document_status_id', $request->query('document_status_id')))
            ->when($request->filled('document_visibility_id'), fn (Builder $query) => $query->where('document_visibility_id', $request->query('document_visibility_id')))
            ->when($request->filled('owner_id'), fn (Builder $query) => $query->where('owner_id', $request->query('owner_id')))
            ->when($request->filled('uploaded_by'), fn (Builder $query) => $query->where('uploaded_by', $request->query('uploaded_by')))
            ->when($request->filled('file_extension'), fn (Builder $query) => $query->where('file_extension', $request->query('file_extension')))
            ->when($request->filled('created_from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->query('created_from')))
            ->when($request->filled('created_to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->query('created_to')))
            ->when($request->filled('project_id'), fn (Builder $query) => $query->whereHas('documentables', fn (Builder $query) => $query->where('documentable_type', Project::class)->where('documentable_id', $request->query('project_id'))))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();
    }
}
