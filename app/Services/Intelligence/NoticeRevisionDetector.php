<?php

namespace App\Services\Intelligence;

use App\Models\TenderObservation;
use Illuminate\Support\Collection;

/**
 * Report tender notices a publisher changed after first publishing them.
 *
 * A revised notice is not evidence of anything improper. Corrections are
 * routine and often good practice: a typo fixed, a date clarified, a category
 * corrected. What matters for transparency is that a change happened and is
 * visible, because a record that silently replaces itself leaves a reader
 * unable to know what was originally advertised.
 *
 * This states what changed, when, and from what to what. It draws no
 * conclusion, and the wording is checked by test to make sure it never starts
 * to.
 *
 * Unlike peer comparison, this needs no cohort. One notice observed twice is
 * enough, so it works from the first crawl rather than waiting for volume.
 */
class NoticeRevisionDetector
{
    /**
     * @return list<array<string, mixed>>
     */
    public function detect(int $publisherId, int $minimumRevisions = 1): array
    {
        $grouped = TenderObservation::query()
            ->where('source_publisher_id', $publisherId)
            ->orderBy('external_id')
            ->orderBy('observed_at')
            ->orderBy('id')
            ->get()
            ->groupBy('external_id')
            ->filter(fn (Collection $rows): bool => $rows->count() > $minimumRevisions)
            ->map(fn (Collection $rows, string $externalId): array => $this->summarize($externalId, array_values($rows->all())))
            ->values()
            ->all();

        return array_values($grouped);
    }

    /**
     * @param  list<TenderObservation>  $ordered
     * @return array<string, mixed>
     */
    private function summarize(string $externalId, array $ordered): array
    {
        $changes = [];
        $count = count($ordered);

        for ($i = 1; $i < $count; $i++) {
            foreach (['status', 'reference_number', 'procurement_nature', 'published_on_raw'] as $field) {
                $before = $ordered[$i - 1]->{$field};
                $after = $ordered[$i]->{$field};

                if ($before !== $after) {
                    $changes[] = [
                        'field' => $field,
                        'from' => $before,
                        'to' => $after,
                        'observed_at' => $this->timestamp($ordered[$i]),
                    ];
                }
            }
        }

        return [
            'external_id' => $externalId,
            'observations' => $count,
            'first_observed_at' => $count > 0 ? $this->timestamp($ordered[0]) : null,
            'last_observed_at' => $count > 0 ? $this->timestamp($ordered[$count - 1]) : null,
            'changes' => $changes,
            'statement' => $this->statement($externalId, $changes),
        ];
    }

    private function timestamp(TenderObservation $observation): ?string
    {
        return $observation->observed_at?->toIso8601String();
    }

    /**
     * Neutral, specific, and never a conclusion about conduct.
     *
     * @param  list<array<string, mixed>>  $changes
     */
    private function statement(string $externalId, array $changes): string
    {
        if ($changes === []) {
            return 'Notice '.$externalId.' was observed more than once with no recorded field changes.';
        }

        $fields = array_values(array_unique(array_map(
            fn (array $change): string => str_replace('_', ' ', (string) $change['field']),
            $changes,
        )));

        return 'Notice '.$externalId.' was republished with '.count($changes).' recorded change'
            .(count($changes) === 1 ? '' : 's').' to '.implode(', ', $fields)
            .'. Notices are revised for many ordinary reasons; this records that a change occurred and what it was, not that anything was wrong.';
    }
}
