<?php

namespace App\Services\Ingestion;

use App\Models\DiscoveredResource;
use App\Models\SourceArtifactVersion;
use App\Models\SourceEndpoint;
use App\Models\SourcePublisher;
use App\Models\TenderObservation;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * What every registered endpoint has actually produced, and why it has not.
 *
 * An endpoint that yields nothing looks identical to one nobody has run: both
 * are a zero. The distinction is the whole of ADR-020 — CAG stays registered
 * and empty because the authorisation is real and the index is missing, which
 * is a recorded decision rather than an oversight — and until now that
 * distinction lived only in prose.
 *
 * So each endpoint is reported with its last success, what it produced, and the
 * failures standing against it grouped by what the publisher actually said. A
 * zero beside "11 × HTTP 404" is a dead route; a zero beside no failures at all
 * is a source nobody has crawled.
 */
class SourceYieldReport
{
    /**
     * @return array{generated_at: string, endpoints: list<array<string, mixed>>, unexplained: list<array<string, mixed>>}
     */
    public function build(): array
    {
        /** @var list<array<string, mixed>> $endpoints */
        $endpoints = [];

        foreach (SourceEndpoint::query()->with('publisher')->orderBy('id')->get() as $endpoint) {
            $publisher = $endpoint->publisher;
            $resources = DiscoveredResource::query()->where('source_endpoint_id', $endpoint->getKey());

            $artifacts = SourceArtifactVersion::query()
                ->whereIn('discovered_resource_id', (clone $resources)->select('id'));

            $failures = $this->failuresFor($endpoint);

            $documents = (clone $artifacts)
                ->whereNotIn('media_type', ['text/html', 'application/json', 'text/plain'])
                ->count();

            // A listing that answers with rows rather than files yields
            // observations, and counting only artifacts reported e-GP — 3,468
            // captured tender notices — as an endpoint that produced nothing
            // and should be looked at. A report that misreads the corpus it is
            // meant to explain is worse than no report.
            $observations = TenderObservation::query()
                ->whereIn('discovered_resource_id', (clone $resources)->select('id'))
                ->count();

            $endpoints[] = [
                'id' => (int) $endpoint->getKey(),
                'publisher' => $publisher instanceof SourcePublisher ? (string) $publisher->slug : 'unattributed',
                'name' => (string) $endpoint->name,
                'connector' => (string) $endpoint->connector_type,
                'health' => (string) $endpoint->health_status,
                'paused' => $endpoint->paused_at !== null,
                'last_crawled_at' => $endpoint->last_crawled_at instanceof CarbonInterface
                    ? $endpoint->last_crawled_at->toIso8601String()
                    : null,
                'resources_discovered' => (clone $resources)->count(),
                'artifacts_archived' => (clone $artifacts)->count(),
                'documents' => $documents,
                'observations' => $observations,
                'failures' => $failures,
                // The sentence a reader needs when the count is zero. Silence
                // where a source produced nothing is how an unnoticed breakage
                // passes for a governance decision.
                'verdict' => $this->verdict($documents, (clone $artifacts)->count(), $observations, $failures, $endpoint),
            ];
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'endpoints' => $endpoints,
            'unexplained' => array_values(array_filter(
                $endpoints,
                fn (array $row): bool => $row['artifacts_archived'] === 0
                    && $row['observations'] === 0
                    && $row['failures'] === [],
            )),
        ];
    }

    /**
     * Failures standing against an endpoint, grouped by what was actually said.
     *
     * @return array<string, int>
     */
    private function failuresFor(SourceEndpoint $endpoint): array
    {
        $resourceIds = DiscoveredResource::query()
            ->where('source_endpoint_id', $endpoint->getKey())
            ->pluck('id')
            ->flip();

        $grouped = [];

        foreach (DB::table('failed_jobs')->get(['payload', 'exception']) as $row) {
            $payload = json_decode((string) $row->payload, true);
            $command = is_array($payload) ? (string) ($payload['data']['command'] ?? '') : '';

            if (preg_match('/resourceId";i:(\d+)/', $command, $matches) !== 1) {
                continue;
            }

            if (! $resourceIds->has((int) $matches[1])) {
                continue;
            }

            $reason = $this->reasonFrom((string) $row->exception);
            $grouped[$reason] = ($grouped[$reason] ?? 0) + 1;
        }

        arsort($grouped);

        return $grouped;
    }

    private function reasonFrom(string $exception): string
    {
        $first = trim(explode("\n", $exception)[0]);

        if (preg_match('/HTTP (\d{3})/', $first, $matches) === 1) {
            return 'HTTP '.$matches[1];
        }

        return match (true) {
            str_contains($first, 'Connection refused') => 'connection refused',
            str_contains($first, 'media type') => 'media type mismatch',
            str_contains($first, 'exceeds the configure') => 'over the byte limit',
            str_contains($first, 'robots') => 'refused by robots',
            default => 'other',
        };
    }

    /**
     * @param  array<string, int>  $failures
     */
    private function verdict(int $documents, int $artifacts, int $observations, array $failures, SourceEndpoint $endpoint): string
    {
        if ($documents > 0) {
            return $documents.' documents.';
        }

        if ($observations > 0) {
            return number_format($observations).' records captured from a listing that publishes rows rather than files.';
        }

        if ($artifacts > 0) {
            return $artifacts.' web pages or records archived, no documents. This publisher answers with records rather than files.';
        }

        if ($failures !== []) {
            $worst = array_key_first($failures);

            return 'Nothing archived. The publisher answered '.$worst.' '.$failures[$worst].' times.';
        }

        if (! $endpoint->last_crawled_at instanceof CarbonInterface) {
            return 'Never crawled.';
        }

        return 'Crawled and produced nothing, with no failure recorded. Look at this one.';
    }
}
