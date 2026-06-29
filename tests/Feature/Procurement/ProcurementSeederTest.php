<?php

use App\Models\ProcurementMethod;
use App\Models\Tender;
use App\Models\TenderCategory;
use App\Models\TenderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds procurement reference data and a baseline tender', function (): void {
    $this->seed();

    expect(ProcurementMethod::query()->where('slug', 'open-tendering')->exists())->toBeTrue()
        ->and(TenderCategory::query()->where('slug', 'works')->exists())->toBeTrue()
        ->and(TenderStatus::query()->where('slug', 'draft')->exists())->toBeTrue()
        ->and(Tender::query()->where('tender_number', 'CVL-TDR-2026-001')->exists())->toBeTrue();
});
