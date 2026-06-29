<?php

use App\Models\Document;
use App\Models\DocumentActivity;
use App\Models\DocumentCategory;
use App\Models\DocumentStatus;
use App\Models\DocumentTag;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\DocumentVisibility;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds document lookup data and supports normalized tagging relationships', function (): void {
    $this->seed();

    expect(DocumentType::query()->where('slug', 'contract')->exists())->toBeTrue()
        ->and(DocumentType::query()->where('slug', 'tender-notice')->exists())->toBeTrue()
        ->and(DocumentCategory::query()->where('slug', 'procurement')->exists())->toBeTrue()
        ->and(DocumentStatus::query()->where('slug', 'active')->exists())->toBeTrue()
        ->and(DocumentVisibility::query()->where('slug', 'internal')->exists())->toBeTrue()
        ->and(DocumentTag::query()->where('slug', 'baseline')->exists())->toBeTrue();

    $document = Document::factory()->create();
    $tag = DocumentTag::factory()->create(['name' => 'Bridge', 'slug' => 'bridge']);
    $project = Project::factory()->create();

    $document->tags()->attach($tag);
    $document->documentables()->create([
        'documentable_type' => Project::class,
        'documentable_id' => $project->id,
        'relationship_type' => 'supporting',
    ]);

    expect($document->tags()->whereKey($tag->id)->exists())->toBeTrue()
        ->and($document->documentables()->where('documentable_id', $project->id)->exists())->toBeTrue();
});

it('keeps document versions and activities immutable', function (): void {
    $version = DocumentVersion::factory()->create();
    $activity = DocumentActivity::factory()->create();

    expect($version->delete())->toBeFalse()
        ->and(DocumentVersion::query()->whereKey($version->id)->exists())->toBeTrue()
        ->and($activity->delete())->toBeFalse()
        ->and(DocumentActivity::query()->whereKey($activity->id)->exists())->toBeTrue();
});
