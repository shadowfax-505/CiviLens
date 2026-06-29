<?php

use App\Models\FiscalYear;
use App\Models\FundingSource;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectPriority;
use App\Models\ProjectStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds project lookup data and a baseline project', function (): void {
    $this->seed();

    expect(ProjectCategory::query()->where('slug', 'transport')->exists())->toBeTrue()
        ->and(ProjectStatus::query()->where('slug', 'planning')->exists())->toBeTrue()
        ->and(ProjectPriority::query()->where('slug', 'high')->exists())->toBeTrue()
        ->and(FundingSource::query()->where('slug', 'public-funds')->exists())->toBeTrue()
        ->and(FiscalYear::query()->where('name', 'FY 2026')->exists())->toBeTrue()
        ->and(Project::query()->where('project_code', 'CVL-2026-001')->exists())->toBeTrue();
});
