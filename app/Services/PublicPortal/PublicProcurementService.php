<?php

namespace App\Services\PublicPortal;

use App\Models\Tender;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PublicProcurementService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Tender>
     */
    public function listing(array $filters = []): LengthAwarePaginator
    {
        return Tender::query()
            ->with([
                'project',
                'agency',
                'method',
                'status',
                'awards' => fn ($query) => $query->where('status', 'approved')->where('public_disclosure_status', 'public'),
                'awards.bidSubmission.bidderOrganization',
                'awards.contract',
            ])
            ->where('is_public', true)
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->when($filters['q'] ?? null, function ($builder, string $query): void {
                $builder->where(function ($builder) use ($query): void {
                    $builder->where('title', 'like', '%'.$query.'%')
                        ->orWhere('tender_number', 'like', '%'.$query.'%')
                        ->orWhere('description', 'like', '%'.$query.'%');
                });
            })
            ->when($filters['procurement_method_id'] ?? null, fn ($builder, int $methodId) => $builder->where('procurement_method_id', $methodId))
            ->when($filters['tender_status_id'] ?? null, fn ($builder, int $statusId) => $builder->where('tender_status_id', $statusId))
            ->latest()
            ->paginate(12)
            ->withQueryString();
    }

    public function findBySlug(string $slug): Tender
    {
        /** @var Tender|null $tender */
        $tender = Tender::query()
            ->with([
                'project',
                'agency',
                'method',
                'status',
                'category',
                'lots',
                'awards' => fn ($query) => $query->where('status', 'approved')->where('public_disclosure_status', 'public'),
                'awards.bidSubmission.bidderOrganization',
                'awards.contract',
            ])
            ->where('is_public', true)
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->where('slug', $slug)
            ->first();

        if ($tender === null) {
            throw new ModelNotFoundException('Tender not found or not publicly accessible.');
        }

        $tender->setRelation('documents', app(PublicDocumentService::class)->forTender($tender));

        return $tender;
    }
}
