<?php

namespace App\Services\Documents;

use App\Models\Document;
use Illuminate\Support\Facades\DB;

class DocumentDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $documentsByType = DB::table('documents')
            ->join('document_types', 'documents.document_type_id', '=', 'document_types.id')
            ->whereNull('documents.archived_at')
            ->whereNull('documents.deleted_at')
            ->select('document_types.name')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('document_types.name')
            ->orderBy('document_types.name')
            ->pluck('aggregate', 'name')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();

        return [
            'total_documents' => Document::query()->whereNull('archived_at')->count(),
            'archived_documents' => Document::query()->whereNotNull('archived_at')->count(),
            'storage_bytes' => (int) Document::query()->sum('file_size'),
            'pending_ocr' => Document::query()->where('ocr_status', 'pending')->count(),
            'missing_metadata' => Document::query()
                ->whereNull('archived_at')
                ->where(fn ($query) => $query->whereNull('description')->orWhereNull('document_category_id'))
                ->count(),
            'version_count' => (int) Document::query()->sum('version_number'),
            'by_type' => $documentsByType,
        ];
    }
}
