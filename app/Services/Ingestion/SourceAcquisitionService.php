<?php

namespace App\Services\Ingestion;

use App\Contracts\Ingestion\ArtifactFetcher;
use App\Data\Ingestion\CrawlCursor;
use App\Exceptions\Ingestion\AcquisitionFailed;
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

            // What a listing said at this moment, kept apart from the
            // operational record it will later update. A discovered resource is
            // overwritten on the next crawl; an observation is not, so a notice
            // the publisher revises leaves a history rather than replacing
            // itself silently.
            $observed = $this->observations->record($resources);

            foreach ($resources as $resource) {
                FetchDiscoveredResourceArtifact::dispatch($resource->id, $run->id, $endpoint->id)->afterCommit();
            }
            $this->activities->record('source.discovery.completed', actor: $actor, endpoint: $endpoint, run: $run, metadata: ['discovered_count' => count($resources)] + $observed);

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
            $this->finishRunWhenComplete($locked);
            $locked->save();
        });
    }

    private function recordFetchCompletion(SourceCrawlRun $run, bool $quarantined): void
    {
        DB::transaction(function () use ($run, $quarantined): void {
            $locked = SourceCrawlRun::query()->lockForUpdate()->findOrFail($run->id);
            $locked->fetched_count++;

            if ($quarantined) {
                $locked->quarantined_count++;
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
