<?php

namespace App\Services\Ingestion;

use App\Contracts\Ingestion\ArtifactFetcher;
use App\Data\Ingestion\CrawlCursor;
use App\Exceptions\Ingestion\AcquisitionFailed;
use App\Jobs\ExtractAcquiredArtifact;
use App\Jobs\FetchDiscoveredResourceArtifact;
use App\Models\DiscoveredResource;
use App\Models\SourceArtifactVersion;
use App\Models\SourceCrawlRun;
use App\Models\SourceEndpoint;
use App\Models\SourcePublisher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SourceAcquisitionService
{
    public function __construct(
        private readonly SourceConnectorRegistry $connectors,
        private readonly ArtifactFetcher $artifactFetcher,
        private readonly SourceActivityRecorder $activities,
        private readonly TenderObservationRecorder $observations,
    ) {}

    public function discover(SourceEndpoint $endpoint, ?User $actor = null): SourceCrawlRun
    {
        $endpoint->loadMissing('publisher');
        $publisher = $endpoint->publisher;

        if (! $publisher instanceof SourcePublisher || $endpoint->isPaused() || ! $publisher->is_active) {
            throw new AcquisitionFailed('Source endpoint or publisher is paused.');
        }

        $cursor = new CrawlCursor(is_array($endpoint->cursor) ? $endpoint->cursor : []);
        $run = SourceCrawlRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'source_endpoint_id' => $endpoint->id,
            'triggered_by' => $actor?->id,
            'status' => 'running',
            'cursor_before' => $cursor->toArray(),
            'started_at' => now(),
        ]);
        $this->activities->record('source.crawl.started', actor: $actor, endpoint: $endpoint, run: $run);

        try {
            $batch = $this->connectors->for($endpoint->connector_type)->discover($endpoint, $cursor);
            $resources = [];

            foreach ($batch->resources as $candidate) {
                $resource = DiscoveredResource::query()->updateOrCreate(
                    [
                        'source_endpoint_id' => $endpoint->id,
                        'canonical_url_hash' => hash('sha256', $candidate->canonicalUrl),
                    ],
                    [
                        'source_crawl_run_id' => $run->id,
                        'canonical_url' => $candidate->canonicalUrl,
                        'discovery_url' => $candidate->discoveryUrl,
                        'external_id' => $candidate->externalId,
                        'resource_type' => $candidate->resourceType,
                        'status' => 'queued',
                        'published_at' => $candidate->publishedAt,
                        'last_seen_at' => now(),
                        'metadata' => $candidate->metadata,
                    ],
                );
                $resources[] = $resource;
            }

            $status = $resources === [] ? 'completed' : 'fetching';
            $run->update([
                'status' => $status,
                'cursor_after' => $batch->nextCursor->toArray(),
                'discovered_count' => count($resources),
                'finished_at' => $resources === [] ? now() : null,
            ]);
            $endpoint->update([
                'cursor' => $batch->nextCursor->toArray(),
                'health_status' => 'healthy',
                'last_error' => null,
                'last_crawled_at' => now(),
            ]);

            // A publisher that answers again has served its penalty.
            $this->forgive($endpoint);

            // What the listing said at this moment, kept apart from the
            // operational record it will later update. A discovered resource is
            // overwritten on the next crawl; an observation is not, so a notice
            // the publisher revises leaves a history rather than replacing
            // itself silently.
            $observed = $this->observations->record($resources);

            // Some listings publish records rather than documents. An e-GP
            // tender row has no retrievable file behind it: the detail servlet
            // answers POST only, so a document fetch returns an empty body and
            // fails. Dispatching one anyway failed seventy jobs and made
            // seventy pointless requests to the publisher, while the row itself
            // had already been captured as an observation.
            $recordOnly = (array) config('civiclens.ingestion.record_only_resource_types', []);
            $fetchable = array_values(array_filter(
                $resources,
                static fn ($resource): bool => ! in_array($resource->resource_type, $recordOnly, true),
            ));

            foreach ($fetchable as $resource) {
                FetchDiscoveredResourceArtifact::dispatch($resource->id, $run->id, $endpoint->id)->afterCommit();
            }
            $this->activities->record('source.discovery.completed', actor: $actor, endpoint: $endpoint, run: $run, metadata: ['discovered_count' => count($resources), 'fetch_dispatched' => count($fetchable)] + $observed);

            $freshRun = $run->fresh();

            if (! $freshRun instanceof SourceCrawlRun) {
                throw new AcquisitionFailed('Source crawl run could not be reloaded.');
            }

            return $freshRun;
        } catch (Throwable $exception) {
            $message = str($exception->getMessage())->squish()->limit(1000)->toString();
            $run->update(['status' => 'failed', 'failure_count' => 1, 'error_summary' => $message, 'finished_at' => now()]);
            $endpoint->update(['health_status' => 'failing', 'last_error' => $message, 'last_crawled_at' => now()]);
            $this->activities->record('source.discovery.failed', actor: $actor, endpoint: $endpoint, run: $run, metadata: ['error' => $message]);

            throw $exception;
        }
    }

    public function acquire(DiscoveredResource $resource, SourceCrawlRun $run): SourceArtifactVersion
    {
        $resource->loadMissing('endpoint.publisher');
        $endpoint = $resource->endpoint;

        if (! $endpoint instanceof SourceEndpoint) {
            throw new AcquisitionFailed('Discovered resource has no source endpoint.');
        }

        $result = $this->artifactFetcher->fetch($resource);
        $existing = $resource->artifactVersions()->where('sha256', $result->sha256)->first();

        if ($existing instanceof SourceArtifactVersion) {
            $resource->update(['status' => $existing->is_quarantined ? 'quarantined' : 'acquired']);
            $this->recordFetchCompletion($run, $existing->is_quarantined);
            $this->activities->record('source.artifact.unchanged', endpoint: $endpoint, run: $run, artifact: $existing);

            return $existing;
        }

        $quarantined = ! $result->malwareScan->isClean();
        $disk = (string) config('civiclens.ingestion.artifact_disk', 'local');

        if ($disk === 'public' || config("filesystems.disks.{$disk}.visibility") === 'public') {
            throw new AcquisitionFailed('Source artifacts require a private storage disk.');
        }

        $path = $this->storagePath($resource, $result->sha256, $result->mediaType, $quarantined);

        if (! Storage::disk($disk)->put($path, $result->content)) {
            throw new AcquisitionFailed('Unable to store the private source artifact.');
        }

        $artifact = DB::transaction(function () use ($resource, $run, $result, $disk, $path, $quarantined): SourceArtifactVersion {
            DiscoveredResource::query()->lockForUpdate()->findOrFail($resource->id);
            $duplicate = SourceArtifactVersion::query()
                ->where('discovered_resource_id', $resource->id)
                ->where('sha256', $result->sha256)
                ->first();

            if ($duplicate instanceof SourceArtifactVersion) {
                return $duplicate;
            }

            $latest = SourceArtifactVersion::query()
                ->where('discovered_resource_id', $resource->id)
                ->lockForUpdate()
                ->orderByDesc('version_number')
                ->first();
            $nextVersion = $latest instanceof SourceArtifactVersion ? $latest->version_number + 1 : 1;

            return SourceArtifactVersion::query()->create([
                'discovered_resource_id' => $resource->id,
                'source_crawl_run_id' => $run->id,
                'supersedes_id' => $latest instanceof SourceArtifactVersion ? $latest->id : null,
                'version_number' => $nextVersion,
                'storage_disk' => $disk,
                'storage_path' => $path,
                'original_filename' => $this->filename($result->retrievalUrl),
                'media_type' => $result->mediaType,
                'byte_size' => $result->byteSize,
                'sha256' => $result->sha256,
                'retrieval_url' => $result->retrievalUrl,
                'http_etag' => $this->header($result->headers, 'etag'),
                'http_last_modified' => $this->header($result->headers, 'last-modified'),
                'response_headers' => $this->safeHeaders($result->headers),
                'malware_status' => $result->malwareScan->status,
                'is_quarantined' => $quarantined,
                'quarantine_reason' => $result->malwareScan->reason,
                'retrieved_at' => now(),
            ]);
        });

        $resource->update(['status' => $artifact->is_quarantined ? 'quarantined' : 'acquired']);
        $this->recordFetchCompletion($run, $artifact->is_quarantined);

        // Nothing read what acquisition stored. After two publishers and ten
        // fetched audit reports there were zero extraction runs, because the
        // extraction spine was never reachable from the pipeline.
        //
        // A quarantined artifact is not read. Quarantine exists to keep a file
        // that failed a malware scan out of the parsers, and extracting it
        // would hand it to exactly those parsers.
        if (! $artifact->is_quarantined) {
            ExtractAcquiredArtifact::dispatch($artifact->id)->afterCommit();
        }
        $this->activities->record(
            $artifact->is_quarantined ? 'source.artifact.quarantined' : 'source.artifact.acquired',
            endpoint: $endpoint,
            run: $run,
            artifact: $artifact,
        );

        return $artifact;
    }

    public function recordFetchFailure(SourceCrawlRun $run, string $message): void
    {
        DB::transaction(function () use ($run, $message): void {
            $locked = SourceCrawlRun::query()->lockForUpdate()->findOrFail($run->id);
            $locked->failure_count++;
            $locked->error_summary = str($message)->squish()->limit(1000)->toString();
            $locked->endpoint()->update(['health_status' => 'failing', 'last_error' => $locked->error_summary]);

            $endpoint = $locked->endpoint;

            if ($endpoint instanceof SourceEndpoint) {
                $this->backOff($endpoint, $locked->error_summary ?? $message);
            }

            $this->finishRunWhenComplete($locked);
            $locked->save();
        });
    }

    /**
     * Leave a publisher that has stopped answering alone for a while.
     *
     * The delay doubles with each consecutive failure, and after enough of them
     * the endpoint pauses and waits for a person. Before this, an endpoint
     * marked failing was dispatched again on the next tick and every queued
     * resource retried: ninety-three failed jobs accumulated against one host,
     * which now refuses the connection outright.
     */
    private function backOff(SourceEndpoint $endpoint, string $reason): void
    {
        // Our own refusals are not the publisher's failures. A paused endpoint
        // declines its own fetches, and counting that as the host not answering
        // would punish a publisher for an operator's decision and extend the
        // backoff every time someone paused it deliberately.
        if ($endpoint->paused_at !== null) {
            return;
        }

        $streak = ((int) $endpoint->failure_streak) + 1;
        $base = max(1, (int) config('civiclens.ingestion.backoff.base_minutes', 15));
        $ceiling = max($base, (int) config('civiclens.ingestion.backoff.max_minutes', 1440));
        $pauseAfter = max(1, (int) config('civiclens.ingestion.backoff.pause_after', 8));

        // Doubling, capped. Shifting rather than multiplying so a long streak
        // cannot overflow into a negative delay.
        $minutes = min($ceiling, $base * (2 ** min(10, $streak - 1)));

        $endpoint->forceFill([
            'failure_streak' => $streak,
            'backoff_until' => now()->addMinutes($minutes),
            'paused_at' => $streak >= $pauseAfter ? ($endpoint->paused_at ?? now()) : $endpoint->paused_at,
            'last_error' => $streak >= $pauseAfter
                ? 'Paused after '.$streak.' consecutive failures: '.$reason
                : $reason,
        ])->save();
    }

    /**
     * A success clears the record. Backing off for ever because of one bad
     * afternoon would quietly retire a healthy publisher.
     */
    private function forgive(SourceEndpoint $endpoint): void
    {
        if ((int) $endpoint->failure_streak === 0 && $endpoint->backoff_until === null) {
            return;
        }

        $endpoint->forceFill(['failure_streak' => 0, 'backoff_until' => null])->save();
    }

    private function recordFetchCompletion(SourceCrawlRun $run, bool $quarantined): void
    {
        DB::transaction(function () use ($run, $quarantined): void {
            $locked = SourceCrawlRun::query()->lockForUpdate()->findOrFail($run->id);
            $locked->fetched_count++;

            if ($quarantined) {
                $locked->quarantined_count++;
            }

            $endpoint = $locked->endpoint;

            if ($endpoint instanceof SourceEndpoint) {
                $this->forgive($endpoint);
            }

            $this->finishRunWhenComplete($locked);
            $locked->save();
        });
    }

    private function finishRunWhenComplete(SourceCrawlRun $run): void
    {
        if ($run->fetched_count + $run->failure_count < $run->discovered_count) {
            return;
        }

        $run->status = $run->failure_count > 0 ? 'completed_with_errors' : 'completed';
        $run->finished_at = now();
    }

    private function storagePath(DiscoveredResource $resource, string $sha256, string $mediaType, bool $quarantined): string
    {
        $bucket = $quarantined ? 'quarantine' : 'approved';
        $endpoint = $resource->endpoint;
        $sourcePublisher = $endpoint instanceof SourceEndpoint ? $endpoint->publisher : null;

        if (! $sourcePublisher instanceof SourcePublisher) {
            throw new AcquisitionFailed('Source artifact has no publisher.');
        }

        $publisher = Str::slug($sourcePublisher->slug);

        return sprintf('ingestion/%s/%s/%d/%s.%s', $bucket, $publisher, $resource->id, $sha256, $this->extension($mediaType));
    }

    private function extension(string $mediaType): string
    {
        return match ($mediaType) {
            'application/pdf' => 'pdf',
            'application/json' => 'json',
            'application/xml', 'text/xml' => 'xml',
            'text/html' => 'html',
            'text/csv' => 'csv',
            'text/plain' => 'txt',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/tiff' => 'tiff',
            default => 'bin',
        };
    }

    private function filename(string $url): ?string
    {
        $basename = basename((string) parse_url($url, PHP_URL_PATH));
        $basename = preg_replace('/[^A-Za-z0-9._-]/', '_', $basename) ?? '';

        return $basename !== '' && $basename !== '.' ? Str::limit($basename, 190, '') : null;
    }

    /**
     * @param  array<string, list<string>>  $headers
     * @return array<string, string>
     */
    private function safeHeaders(array $headers): array
    {
        $safe = [];

        foreach (['content-type', 'content-length', 'etag', 'last-modified'] as $name) {
            $value = $this->header($headers, $name);

            if ($value !== null) {
                $safe[$name] = $value;
            }
        }

        return $safe;
    }

    /** @param array<string, list<string>> $headers */
    private function header(array $headers, string $name): ?string
    {
        foreach ($headers as $header => $values) {
            if (strtolower($header) === $name) {
                return isset($values[0]) ? Str::limit($values[0], 500, '') : null;
            }
        }

        return null;
    }
}
