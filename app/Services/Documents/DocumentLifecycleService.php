<?php

namespace App\Services\Documents;

use App\Events\DocumentArchived;
use App\Events\DocumentMetadataUpdated;
use App\Events\DocumentUpdated;
use App\Events\DocumentUploaded;
use App\Events\DocumentVersionCreated;
use App\Jobs\ExtractDocumentMetadata;
use App\Jobs\GenerateDocumentThumbnail;
use App\Jobs\IndexDocumentForSearch;
use App\Jobs\ProcessDocumentAiMetadata;
use App\Jobs\RunDocumentOcr;
use App\Jobs\ScanDocumentForViruses;
use App\Models\AdministrativeUnion;
use App\Models\Agency;
use App\Models\Budget;
use App\Models\Contract;
use App\Models\ContractorProfile;
use App\Models\Country;
use App\Models\District;
use App\Models\Division;
use App\Models\Document;
use App\Models\DocumentActivity;
use App\Models\DocumentVersion;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Tender;
use App\Models\Upazila;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DocumentLifecycleService
{
    public function __construct(private readonly DocumentStorageService $storage) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upload(array $attributes, UploadedFile $file, User $actor): Document
    {
        return DB::transaction(function () use ($attributes, $file, $actor): Document {
            $fileMetadata = $this->storage->store($file);

            /** @var Document $document */
            $document = Document::query()->create(array_merge($this->documentAttributes($attributes), $fileMetadata, [
                'uuid' => (string) Str::uuid(),
                'owner_id' => $attributes['owner_id'] ?? $actor->id,
                'uploaded_by' => $actor->id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
                'version_number' => 1,
                'ocr_status' => 'pending',
                'index_status' => 'pending',
                'preview_status' => 'pending',
            ]));

            $version = $this->createVersion($document, $fileMetadata, $actor, 1, 'Initial upload.', true);
            $this->syncTags($document, $attributes['tag_ids'] ?? []);
            $this->syncDocumentable($document, $attributes);
            $this->recordActivity($document, 'document.uploaded', 'Document uploaded.', $actor, null, $document->only(['title', 'original_filename']), $version);
            $this->queueProcessors($document);

            $documentTypeSlug = $document->type?->getAttribute('slug');

            DocumentUploaded::dispatch($document->storage_disk, $document->storage_path, $actor, is_string($documentTypeSlug) ? $documentTypeSlug : null);
            DocumentVersionCreated::dispatch($version, $actor);

            return $document;
        });
    }

    public function replaceFile(Document $document, UploadedFile $file, User $actor, ?string $reason = null): Document
    {
        return DB::transaction(function () use ($document, $file, $actor, $reason): Document {
            $fileMetadata = $this->storage->store($file);
            $newVersion = $document->version_number + 1;

            DocumentVersion::query()
                ->where('document_id', $document->id)
                ->update(['is_current' => false]);

            $document->forceFill(array_merge($fileMetadata, [
                'uploaded_by' => $actor->id,
                'updated_by' => $actor->id,
                'version_number' => $newVersion,
                'ocr_status' => 'pending',
                'index_status' => 'pending',
                'preview_status' => 'pending',
            ]))->save();

            $version = $this->createVersion($document, $fileMetadata, $actor, $newVersion, $reason, true);
            $this->recordActivity($document, 'document.version_created', 'Document version created.', $actor, null, ['version_number' => $newVersion], $version);
            $this->queueProcessors($document);

            DocumentVersionCreated::dispatch($version, $actor);

            return $document->fresh() ?? $document;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateMetadata(Document $document, array $attributes, User $actor): Document
    {
        $oldValues = $document->only(['title', 'description', 'document_type_id', 'document_category_id', 'document_status_id', 'document_visibility_id', 'language']);

        $document->forceFill(array_merge($this->documentAttributes($attributes), [
            'updated_by' => $actor->id,
        ]))->save();

        $this->syncTags($document, $attributes['tag_ids'] ?? []);
        $this->syncDocumentable($document, $attributes);
        $this->recordActivity($document, 'document.metadata_updated', 'Document metadata updated.', $actor, $oldValues, $document->only(array_keys($oldValues)));

        DocumentUpdated::dispatch($document, $actor);
        DocumentMetadataUpdated::dispatch($document, $actor);

        return $document;
    }

    public function archive(Document $document, User $actor): void
    {
        $document->forceFill([
            'archived_at' => now(),
            'updated_by' => $actor->id,
        ])->save();

        $this->recordActivity($document, 'document.archived', 'Document archived.', $actor);
        DocumentArchived::dispatch($document, $actor);
    }

    public function restore(Document $document, User $actor): void
    {
        $document->forceFill([
            'archived_at' => null,
            'updated_by' => $actor->id,
        ])->save();

        $this->recordActivity($document, 'document.restored', 'Document restored.', $actor);
    }

    public function recordDownload(Document $document, User $actor): void
    {
        $this->recordActivity($document, 'document.downloaded', 'Document downloaded.', $actor);
    }

    /**
     * @param  array<int, int|string>  $documentIds
     * @param  array<int, int|string>  $tagIds
     */
    public function bulkTag(array $documentIds, array $tagIds, User $actor): void
    {
        Document::query()->whereIn('id', $documentIds)->get()->each(function (Document $document) use ($tagIds, $actor): void {
            $document->tags()->syncWithoutDetaching($tagIds);
            $this->recordActivity($document, 'document.bulk_tagged', 'Document tags updated in bulk.', $actor, null, ['tag_ids' => $tagIds]);
        });
    }

    /**
     * @param  array<int, int|string>  $documentIds
     */
    public function bulkArchive(array $documentIds, User $actor): void
    {
        Document::query()->whereIn('id', $documentIds)->get()->each(fn (Document $document) => $this->archive($document, $actor));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function documentAttributes(array $attributes): array
    {
        return [
            'document_type_id' => $attributes['document_type_id'],
            'document_category_id' => $attributes['document_category_id'] ?? null,
            'document_status_id' => $attributes['document_status_id'],
            'document_visibility_id' => $attributes['document_visibility_id'],
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'language' => $attributes['language'] ?? null,
            'page_count' => $attributes['page_count'] ?? null,
        ];
    }

    /**
     * @param  array<string, int|string>  $fileMetadata
     */
    private function createVersion(Document $document, array $fileMetadata, User $actor, int $versionNumber, ?string $reason, bool $current): DocumentVersion
    {
        /** @var DocumentVersion $version */
        $version = DocumentVersion::query()->create(array_merge($fileMetadata, [
            'document_id' => $document->id,
            'version_number' => $versionNumber,
            'uploaded_by' => $actor->id,
            'reason' => $reason,
            'is_current' => $current,
        ]));

        return $version;
    }

    /**
     * @param  array<int, int|string>  $tagIds
     */
    private function syncTags(Document $document, array $tagIds): void
    {
        $document->tags()->sync(array_values(array_filter($tagIds)));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function syncDocumentable(Document $document, array $attributes): void
    {
        if (empty($attributes['documentable_type']) || empty($attributes['documentable_id'])) {
            return;
        }

        $class = $this->documentableClass((string) $attributes['documentable_type']);

        $document->documentables()->updateOrCreate(
            [
                'documentable_type' => $class,
                'documentable_id' => (int) $attributes['documentable_id'],
            ],
            ['relationship_type' => $attributes['relationship_type'] ?? 'supporting'],
        );
    }

    private function documentableClass(string $type): string
    {
        return match ($type) {
            'project' => Project::class,
            'budget' => Budget::class,
            'tender' => Tender::class,
            'contract' => Contract::class,
            'contractor' => ContractorProfile::class,
            'organization' => Organization::class,
            'agency' => Agency::class,
            'country' => Country::class,
            'division' => Division::class,
            'district' => District::class,
            'upazila' => Upazila::class,
            'union' => AdministrativeUnion::class,
            'ward' => Ward::class,
            default => throw new InvalidArgumentException('Unsupported document relationship type.'),
        };
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function recordActivity(Document $document, string $event, string $description, User $actor, ?array $oldValues = null, ?array $newValues = null, ?DocumentVersion $version = null): DocumentActivity
    {
        /** @var DocumentActivity $activity */
        $activity = DocumentActivity::query()->create([
            'document_id' => $document->id,
            'document_version_id' => $version?->id,
            'actor_id' => $actor->id,
            'event' => $event,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);

        return $activity;
    }

    private function queueProcessors(Document $document): void
    {
        GenerateDocumentThumbnail::dispatch($document->id);
        ScanDocumentForViruses::dispatch($document->id);
        ExtractDocumentMetadata::dispatch($document->id);
        RunDocumentOcr::dispatch($document->id);
        IndexDocumentForSearch::dispatch($document->id);
        ProcessDocumentAiMetadata::dispatch($document->id);
    }
}
