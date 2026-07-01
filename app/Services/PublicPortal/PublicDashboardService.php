<?php

namespace App\Services\PublicPortal;

use App\Models\Document;
use App\Models\Project;
use App\Models\Tender;

class PublicDashboardService
{
    /**
     * @return array<string, int>
     */
    public function summary(): array
    {
        return [
            'public_projects' => Project::query()->where('is_public', true)->where('is_active', true)->whereNull('archived_at')->count(),
            'public_tenders' => Tender::query()->where('is_public', true)->where('is_active', true)->whereNull('archived_at')->count(),
            'public_documents' => Document::query()->whereNull('archived_at')->whereHas('visibility', fn ($builder) => $builder->where('slug', 'public'))->count(),
        ];
    }
}
