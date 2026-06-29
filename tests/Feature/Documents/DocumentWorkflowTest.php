<?php

use App\Events\DocumentArchived;
use App\Events\DocumentUploaded;
use App\Events\DocumentVersionCreated;
use App\Jobs\ExtractDocumentMetadata;
use App\Jobs\GenerateDocumentThumbnail;
use App\Jobs\IndexDocumentForSearch;
use App\Jobs\RunDocumentOcr;
use App\Models\Document;
use App\Models\DocumentActivity;
use App\Models\DocumentCategory;
use App\Models\DocumentStatus;
use App\Models\DocumentTag;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\DocumentVisibility;
use App\Models\Project;
use App\Models\User;
use App\Services\Documents\DocumentLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('stores document uploads with checksums versions activities events and queued processors', function (): void {
    Storage::fake('local');
    Event::fake([DocumentUploaded::class, DocumentVersionCreated::class]);
    Queue::fake();

    $user = User::factory()->create();
    $project = Project::factory()->create();
    $file = UploadedFile::fake()->create('contract.pdf', 32, 'application/pdf');

    $document = app(DocumentLifecycleService::class)->upload([
        'document_type_id' => DocumentType::factory()->create(['slug' => 'contract'])->id,
        'document_category_id' => DocumentCategory::factory()->create(['slug' => 'procurement'])->id,
        'document_status_id' => DocumentStatus::factory()->create(['slug' => 'active'])->id,
        'document_visibility_id' => DocumentVisibility::factory()->create(['slug' => 'internal'])->id,
        'title' => 'Bridge Contract',
        'description' => 'Signed construction contract.',
        'language' => 'en',
        'documentable_type' => 'project',
        'documentable_id' => $project->id,
        'tag_ids' => [DocumentTag::factory()->create()->id],
    ], $file, $user);

    expect($document)->toBeInstanceOf(Document::class)
        ->and($document->version_number)->toBe(1)
        ->and($document->checksum)->not->toBeEmpty()
        ->and($document->documentables()->where('documentable_type', Project::class)->exists())->toBeTrue()
        ->and(DocumentVersion::query()->where('document_id', $document->id)->where('version_number', 1)->where('is_current', true)->exists())->toBeTrue()
        ->and(DocumentActivity::query()->where('document_id', $document->id)->where('event', 'document.uploaded')->exists())->toBeTrue();

    Storage::disk('local')->assertExists($document->storage_path);
    Event::assertDispatched(DocumentUploaded::class);
    Event::assertDispatched(DocumentVersionCreated::class);
    Queue::assertPushed(GenerateDocumentThumbnail::class);
    Queue::assertPushed(RunDocumentOcr::class);
    Queue::assertPushed(ExtractDocumentMetadata::class);
    Queue::assertPushed(IndexDocumentForSearch::class);
});

it('creates replacement versions without overwriting previous files', function (): void {
    Storage::fake('local');
    Event::fake([DocumentVersionCreated::class]);

    $user = User::factory()->create();
    $document = app(DocumentLifecycleService::class)->upload([
        'document_type_id' => DocumentType::factory()->create()->id,
        'document_category_id' => DocumentCategory::factory()->create()->id,
        'document_status_id' => DocumentStatus::factory()->create(['slug' => 'active'])->id,
        'document_visibility_id' => DocumentVisibility::factory()->create(['slug' => 'internal'])->id,
        'title' => 'Progress Report',
        'language' => 'en',
    ], UploadedFile::fake()->create('progress-v1.pdf', 12, 'application/pdf'), $user);

    $firstPath = $document->storage_path;

    $updated = app(DocumentLifecycleService::class)->replaceFile(
        $document,
        UploadedFile::fake()->create('progress-v2.pdf', 16, 'application/pdf'),
        $user,
        'Corrected pages.',
    );

    expect($updated->version_number)->toBe(2)
        ->and(DocumentVersion::query()->where('document_id', $document->id)->count())->toBe(2)
        ->and(DocumentVersion::query()->where('document_id', $document->id)->where('version_number', 1)->where('is_current', false)->exists())->toBeTrue()
        ->and(Storage::disk('local')->exists($firstPath))->toBeTrue()
        ->and(Storage::disk('local')->exists($updated->storage_path))->toBeTrue();

    Event::assertDispatched(DocumentVersionCreated::class);
});

it('archives and restores documents with immutable activities', function (): void {
    Event::fake([DocumentArchived::class]);

    $document = Document::factory()->create();

    app(DocumentLifecycleService::class)->archive($document, User::factory()->create());
    expect($document->fresh()->archived_at)->not->toBeNull()
        ->and(DocumentActivity::query()->where('document_id', $document->id)->where('event', 'document.archived')->exists())->toBeTrue();

    Event::assertDispatched(DocumentArchived::class);

    app(DocumentLifecycleService::class)->restore($document, User::factory()->create());
    expect($document->fresh()->archived_at)->toBeNull()
        ->and(DocumentActivity::query()->where('document_id', $document->id)->where('event', 'document.restored')->exists())->toBeTrue();
});
