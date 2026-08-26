<?php

namespace App\Services\Publication;

use App\Models\ExtractionField;
use App\Models\PublicationDocument;
use App\Models\PublicationFigure;
use App\Models\PublicationNotice;
use App\Models\PublicationSnapshot;
use App\Models\SourceArtifactVersion;
use App\Models\SourcePublisher;
use App\Models\TenderObservation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turn what the pipeline currently believes into a set fit to be published.
 *
 * This is the step that was missing. Extraction, scoring and calibration all
 * worked and wrote to their own tables; nothing carried the results anywhere a
 * reader could reach, so the public site served demonstration rows next to a
 * working pipeline.
 *
 * What it deliberately does not do is fill the domain tables. `tenders`
 * requires a project, a budget, an agency, a procurement method, a category, a
 * status and a title. An e-GP listing row carries none of those and an audit
 * page carries a figure, not a contract. Writing either into `tenders` would
 * mean inventing the six columns that make it a tender, which is the one thing
 * a project built on refusing to guess must not do. So the published shape is
 * the shape the evidence supports: documents, the certified figures read off
 * their pages, and the notices a publisher listed.
 */
class PublicationSnapshotBuilder
{
    /**
     * Only an accepted field is publishable. A deferral is the system saying it
     * will not stand behind the value, and publishing one under a caveat is
     * still publishing it.
     */
    private const PUBLISHABLE_DECISION = 'accepted';

    /**
     * What an archived web page or API record is served as. These are real
     * acquisitions and belong in the snapshot, but calling 476 of them 476
     * documents would overstate the corpus by a factor of forty.
     */
    private const NON_DOCUMENT_MEDIA_TYPES = ['text/html', 'application/json', 'text/plain'];

    public function build(string $label, ?string $notes = null): PublicationSnapshot
    {
        return DB::transaction(function () use ($label, $notes): PublicationSnapshot {
            $snapshot = PublicationSnapshot::create([
                'label' => $label,
                'requested_alpha' => (float) config('civiclens.extraction.default_alpha', 0.05),
                'calibration_digest' => $this->calibrationDigest(),
                'notes' => $notes,
                'built_at' => now(),
            ]);

            $documents = $this->copyDocuments($snapshot);
            $figures = $this->copyFigures($snapshot, $documents);
            $notices = $this->copyNotices($snapshot);

            // A document that certified nothing stays in the snapshot with a
            // figure count of zero. ADR-020 settled this for the empty CAG
            // endpoint and it applies here: removing the zeroes would make the
            // corpus look like it certifies everything it holds, and most of
            // what it holds is archived web pages that never could.
            $this->recordFigureCounts($figures['per_document']);

            $snapshot->forceFill([
                'document_count' => $documents->count(),
                'figure_count' => $figures['count'],
                'skipped_figure_count' => $figures['skipped'],
                'notice_count' => $notices,
            ])->save();

            return $snapshot->refresh();
        });
    }

    /**
     * Publish a built snapshot, superseding whatever was public before.
     *
     * The previous snapshot is left in place. A published figure that someone
     * has cited has to stay resolvable after the next build, which is the whole
     * reason a snapshot copies its values instead of pointing at live rows.
     */
    public function publish(PublicationSnapshot $snapshot): PublicationSnapshot
    {
        if ($snapshot->isPublished()) {
            return $snapshot;
        }

        $snapshot->forceFill(['published_at' => now()])->save();

        return $snapshot;
    }

    /**
     * Every document that produced at least one extraction run.
     *
     * @return Collection<int, PublicationDocument>
     */
    private function copyDocuments(PublicationSnapshot $snapshot): Collection
    {
        $documents = new Collection;

        SourceArtifactVersion::query()
            // One row per distinct file. The same document reached the archive
            // from two addresses often enough that 75 of 673 artifacts are a
            // second copy, and a corpus that lists a document twice overstates
            // itself in the one number a reader is most likely to quote.
            ->canonical()
            ->whereHas('extractionRuns')
            ->where('is_quarantined', false)
            ->with(['discoveredResource.endpoint.publisher', 'extractionRuns'])
            ->orderBy('id')
            ->chunkById(200, function (Collection $artifacts) use ($snapshot, $documents): void {
                foreach ($artifacts as $artifact) {
                    $resource = $artifact->discoveredResource;
                    $endpoint = $resource?->endpoint;
                    $publisher = $endpoint?->publisher;

                    if ($resource === null || ! $publisher instanceof SourcePublisher) {
                        continue;
                    }

                    $documents->push(PublicationDocument::create([
                        'publication_snapshot_id' => $snapshot->id,
                        'source_artifact_version_id' => $artifact->id,
                        'publisher_slug' => $publisher->slug,
                        'publisher_name' => $publisher->name,
                        'attribution_name' => $publisher->attribution_name,
                        'rights_decision' => $publisher->rights_decision,
                        'source_url' => (string) ($artifact->retrieval_url ?: $resource->canonical_url),
                        'original_filename' => $artifact->original_filename,
                        'media_type' => $artifact->media_type,
                        'is_document' => ! in_array(strtolower((string) $artifact->media_type), self::NON_DOCUMENT_MEDIA_TYPES, true),
                        'byte_size' => $artifact->byte_size,
                        'sha256' => $artifact->sha256,
                        'page_count' => (int) $artifact->extractionRuns->max('page_count'),
                        'retrieved_at' => $artifact->retrieved_at,
                    ]));
                }
            });

        return $documents;
    }

    /**
     * @param  Collection<int, PublicationDocument>  $documents
     * @return array{count: int, skipped: int, per_document: array<int, int>}
     */
    private function copyFigures(PublicationSnapshot $snapshot, Collection $documents): array
    {
        $byArtifact = $documents->keyBy('source_artifact_version_id');

        $count = 0;
        $skipped = 0;
        $perDocument = [];

        ExtractionField::query()
            ->where('decision', self::PUBLISHABLE_DECISION)
            // A reviewer who marked a value wrong outranks every threshold that
            // exists. `decide-fields` deliberately leaves adjudicated fields
            // alone — deciding the calibration set from its own calibration
            // would be circular — so their decision keeps whatever it held when
            // they were judged, and two values a person had explicitly rejected
            // were published as certified figures.
            //
            // Nothing downstream of a human saying "this is not what the page
            // says" may publish it.
            ->where(fn ($query) => $query->whereNull('is_correct')->orWhere('is_correct', true))
            ->with(['page', 'page.run'])
            ->orderBy('id')
            ->chunkById(500, function (Collection $fields) use ($snapshot, $byArtifact, &$count, &$skipped, &$perDocument): void {
                $rows = [];

                foreach ($fields as $field) {
                    $page = $field->page;
                    $artifactId = $page?->run?->source_artifact_version_id;
                    $document = $artifactId === null ? null : $byArtifact->get($artifactId);

                    // A certified value whose document cannot be named is not
                    // publishable: the evidence is the point, and a figure
                    // without a source is an assertion. Counted on the snapshot
                    // so a shortfall is visible rather than silent.
                    if ($page === null || ! $document instanceof PublicationDocument) {
                        $skipped++;

                        continue;
                    }

                    $perDocument[$document->id] = ($perDocument[$document->id] ?? 0) + 1;

                    $rows[] = [
                        'publication_snapshot_id' => $snapshot->id,
                        'publication_document_id' => $document->id,
                        'extraction_field_id' => $field->id,
                        'field_kind' => (string) $field->field_key,
                        'field_type' => (string) $field->field_type,
                        'extracted_value' => (string) $field->extracted_value,
                        'normalized_value' => $field->normalized_value,
                        'page_number' => (int) $page->page_number,
                        'script_class' => $field->script_class,
                        'certified_alpha' => $field->decision_alpha,
                        'decision_basis' => $field->decision_basis,
                        'publisher_group' => $field->publisher_group,
                        'nonconformity_score' => $field->nonconformity_score,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($rows !== []) {
                    PublicationFigure::query()->insert($rows);
                    $count += count($rows);
                }
            });

        return ['count' => $count, 'skipped' => $skipped, 'per_document' => $perDocument];
    }

    /**
     * @param  array<int, int>  $perDocument
     */
    private function recordFigureCounts(array $perDocument): void
    {
        foreach ($perDocument as $documentId => $figureCount) {
            PublicationDocument::query()->whereKey($documentId)->update(['figure_count' => $figureCount]);
        }
    }

    private function copyNotices(PublicationSnapshot $snapshot): int
    {
        $count = 0;

        TenderObservation::query()
            ->with(['publisher', 'discoveredResource'])
            ->orderBy('id')
            ->chunkById(500, function (Collection $observations) use ($snapshot, &$count): void {
                $rows = [];

                foreach ($observations as $observation) {
                    $rows[] = [
                        'publication_snapshot_id' => $snapshot->id,
                        'tender_observation_id' => $observation->id,
                        'publisher_slug' => (string) ($observation->publisher?->slug ?: 'unknown'),
                        'external_id' => $observation->external_id,
                        'reference_number' => $observation->reference_number,
                        'status' => $observation->status,
                        'procurement_nature' => $observation->procurement_nature,
                        'published_on' => $observation->published_on,
                        'source_url' => $observation->discoveredResource?->canonical_url,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($rows !== []) {
                    PublicationNotice::query()->insert($rows);
                    $count += count($rows);
                }
            });

        return $count;
    }

    /**
     * Identify the calibration these decisions came from.
     *
     * Two snapshots with identical counts can rest on different thresholds.
     * Hashing the group thresholds makes that difference visible instead of
     * leaving a reader to assume the numbers mean the same thing.
     */
    private function calibrationDigest(): ?string
    {
        $groups = DB::table('extraction_fields')
            ->where('decision', self::PUBLISHABLE_DECISION)
            ->selectRaw('publisher_group, script_class, decision_alpha, decision_basis, count(*) as n')
            ->groupBy('publisher_group', 'script_class', 'decision_alpha', 'decision_basis')
            ->orderBy('publisher_group')
            ->orderBy('script_class')
            ->get()
            ->map(fn (object $row): string => implode('|', [
                (string) $row->publisher_group,
                (string) $row->script_class,
                (string) $row->decision_alpha,
                (string) $row->decision_basis,
                (string) $row->n,
            ]))
            ->all();

        return $groups === [] ? null : hash('sha256', implode("\n", $groups));
    }
}
