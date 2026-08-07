<?php

use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\ExtractionRun;
use App\Models\SourceArtifactVersion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('records an extraction run against an immutable source artifact version', function (): void {
    $artifact = SourceArtifactVersion::factory()->create();

    $run = ExtractionRun::factory()->create([
        'source_artifact_version_id' => $artifact->id,
        'engine' => 'poppler',
        'engine_version' => '25.01.0',
    ]);

    $run->refresh();

    expect($run->artifactVersion->is($artifact))->toBeTrue()
        ->and($run->status)->toBe('running')
        ->and($run->review_status)->toBe('pending')
        ->and($run->routing_decision)->toBe('pending')
        ->and($run->pages_native)->toBe(0)
        ->and($run->pages_abstained)->toBe(0);
});

it('keeps source artifacts undeletable while an extraction run references them', function (): void {
    $artifact = SourceArtifactVersion::factory()->create();
    ExtractionRun::factory()->create(['source_artifact_version_id' => $artifact->id]);

    expect($artifact->delete())->toBeFalse()
        ->and(SourceArtifactVersion::query()->whereKey($artifact->getKey())->exists())->toBeTrue();
});

it('rejects two extraction pages claiming the same page number in one run', function (): void {
    $run = ExtractionRun::factory()->create();
    ExtractionPage::factory()->create(['extraction_run_id' => $run->id, 'page_number' => 3]);

    expect(fn () => ExtractionPage::factory()->create(['extraction_run_id' => $run->id, 'page_number' => 3]))
        ->toThrow(QueryException::class);
});

it('records the per-page routing decision and confidence the paper is measured on', function (): void {
    $run = ExtractionRun::factory()->create();

    $native = ExtractionPage::factory()->create([
        'extraction_run_id' => $run->id,
        'page_number' => 1,
        'extraction_path' => 'native',
        'script_class' => 'en',
        'confidence' => 1.0,
    ]);
    $abstained = ExtractionPage::factory()->create([
        'extraction_run_id' => $run->id,
        'page_number' => 2,
        'extraction_path' => 'abstained',
        'script_class' => 'bn',
        'confidence' => 0.2135,
    ]);

    expect($run->pages()->count())->toBe(2)
        ->and($native->confidence)->toBe(1.0)
        ->and($abstained->confidence)->toBe(0.2135)
        ->and($abstained->script_class)->toBe('bn');
});

it('scopes extraction fields to the calibratable subset', function (): void {
    $run = ExtractionRun::factory()->create();
    ExtractionField::factory()->create(['extraction_run_id' => $run->id, 'is_correct' => true, 'nonconformity_score' => 0.1]);
    ExtractionField::factory()->create(['extraction_run_id' => $run->id, 'is_correct' => null, 'nonconformity_score' => 0.4]);
    ExtractionField::factory()->create(['extraction_run_id' => $run->id, 'is_correct' => false, 'nonconformity_score' => null]);

    expect(ExtractionField::query()->calibratable()->count())->toBe(1)
        ->and(ExtractionField::query()->count())->toBe(3);
});

it('partitions extraction fields by publisher and script class for group-conditional calibration', function (): void {
    $run = ExtractionRun::factory()->create();

    foreach ([['mof', 'bn', true], ['mof', 'bn', false], ['mof', 'en', true], ['cptu', 'bn', true]] as [$publisher, $script, $correct]) {
        ExtractionField::factory()->create([
            'extraction_run_id' => $run->id,
            'publisher_group' => $publisher,
            'script_class' => $script,
            'is_correct' => $correct,
            'nonconformity_score' => 0.25,
        ]);
    }

    expect(ExtractionField::query()->inGroup('mof', 'bn')->calibratable()->count())->toBe(2)
        ->and(ExtractionField::query()->inGroup('mof', 'en')->calibratable()->count())->toBe(1)
        ->and(ExtractionField::query()->inGroup('cptu', 'bn')->calibratable()->count())->toBe(1)
        ->and(ExtractionField::query()->inGroup('cptu', 'en')->calibratable()->count())->toBe(0);
});

it('cascades pages and fields when an extraction run is discarded', function (): void {
    $run = ExtractionRun::factory()->create();
    $page = ExtractionPage::factory()->create(['extraction_run_id' => $run->id]);
    ExtractionField::factory()->create(['extraction_run_id' => $run->id, 'extraction_page_id' => $page->id]);

    $run->delete();

    expect(ExtractionPage::query()->count())->toBe(0)
        ->and(ExtractionField::query()->count())->toBe(0);
});

it('leaves the legacy document ocr metadata projection untouched', function (): void {
    expect(Schema::hasTable('document_ocr_metadata'))->toBeTrue()
        ->and(Schema::hasColumns('document_ocr_metadata', ['extracted_text', 'confidence', 'ocr_engine']))->toBeTrue()
        ->and(DB::table('document_ocr_metadata')->count())->toBe(0);
});
