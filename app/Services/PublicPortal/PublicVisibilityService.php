<?php

namespace App\Services\PublicPortal;

use App\Models\Agency;
use App\Models\ContractorProfile;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Tender;

class PublicVisibilityService
{
    public function projectIsPublic(Project $project): bool
    {
        return $project->is_public === true && $project->is_active === true && $project->archived_at === null;
    }

    public function agencyIsPublic(Agency $agency): bool
    {
        return $agency->status === 'active' && $agency->trashed() === false;
    }

    public function tenderIsPublic(Tender $tender): bool
    {
        return $tender->is_public === true && $tender->is_active === true && $tender->archived_at === null;
    }

    public function documentIsPublic(Document $document): bool
    {
        return $document->archived_at === null
            && $document->visibility()->where('slug', 'public')->exists()
            && $document->status()->where('slug', '!=', 'archived')->exists();
    }

    public function organizationIsPublic(Organization $organization): bool
    {
        if ($organization->status !== 'active' || $organization->archived_at !== null) {
            return false;
        }

        $profile = $organization->profile;
        if (! $profile instanceof ContractorProfile) {
            return false;
        }

        return $profile->is_public === true
            && $profile->is_active === true
            && $profile->is_suspended === false
            && $profile->is_blacklisted === false
            && $profile->archived_at === null;
    }
}
