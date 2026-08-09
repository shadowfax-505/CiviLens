<?php

namespace App\Services\Ingestion;

use App\Models\DiscoveredResource;
use App\Models\SourceEndpoint;
use App\Models\TenderObservation;
use Carbon\CarbonImmutable;

/**
 * Record what a publisher said about a tender, without claiming it is true.
 *
 * An observation is evidence of a claim, not a verified fact. It is stored
 * separately from operational records and never overwrites one, so a publisher
 * correcting itself appears as history rather than erasing what was said before.
 */
class TenderObservationRecorder
{
    /**
     * @param  list<DiscoveredResource>  $resources
     * @return array<string, int>
     */
    public function record(array $resources, ?CarbonImmutable $observedAt = null): array
    {
        $observedAt ??= CarbonImmutable::now();
        $recorded = 0;
        $unchanged = 0;
        $skipped = 0;

        foreach ($resources as $resource) {
            $metadata = $this->metadata($resource);
            $externalId = $resource->external_id;

            if (! is_string($externalId) || trim($externalId) === '') {
                $skipped++;

                continue;
            }

            $endpoint = $resource->endpoint;
            $publisherId = $endpoint instanceof SourceEndpoint ? $endpoint->source_publisher_id : null;

            if ($publisherId === null) {
                $skipped++;

                continue;
            }

            $attributes = [
                'reference_number' => $this->text($metadata['reference_number'] ?? null, 300),
                'status' => $this->text($metadata['status'] ?? null, 64),
                'procurement_nature' => $this->text($metadata['procurement_nature'] ?? null, 96),
                'published_on_raw' => $this->text($metadata['published_on'] ?? null, 64),
            ];

            $hash = hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR));

            $existing = TenderObservation::query()
                ->where('source_publisher_id', $publisherId)
                ->where('external_id', $externalId)
                ->where('observation_hash', $hash)
                ->exists();

            if ($existing) {
                $unchanged++;

                continue;
            }

            TenderObservation::query()->create([
                'source_publisher_id' => $publisherId,
                'discovered_resource_id' => $resource->getKey(),
                'external_id' => $externalId,
                ...$attributes,
                'published_on' => $this->date($attributes['published_on_raw']),
                'observation_hash' => $hash,
                'observed_at' => $observedAt,
            ]);

            $recorded++;
        }

        return ['recorded' => $recorded, 'unchanged' => $unchanged, 'skipped' => $skipped];
    }

    /** @return array<string, mixed> */
    private function metadata(DiscoveredResource $resource): array
    {
        $metadata = $resource->metadata;

        if (! is_array($metadata)) {
            return [];
        }

        $typed = [];

        foreach ($metadata as $key => $value) {
            if (is_string($key)) {
                $typed[$key] = $value;
            }
        }

        return $typed;
    }

    private function text(mixed $value, int $limit): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $clean = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? (string) $value);

        return $clean === '' ? null : mb_substr($clean, 0, $limit);
    }

    /**
     * Publishers write dates several ways and some are ambiguous. A date that
     * cannot be read unambiguously is left null rather than guessed, because a
     * wrong date would silently reorder a timeline.
     */
    private function date(?string $raw): ?CarbonImmutable
    {
        if ($raw === null) {
            return null;
        }

        $normalized = str_replace(['.', '/'], '-', $raw);

        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $normalized, $m) === 1) {
            return $this->build((int) $m[3], (int) $m[2], (int) $m[1]);
        }

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $normalized, $m) === 1) {
            return $this->build((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return null;
    }

    private function build(int $year, int $month, int $day): ?CarbonImmutable
    {
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31 || ! checkdate($month, $day, $year)) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day)?->startOfDay();
    }
}
