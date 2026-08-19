<?php

namespace App\Services\Screening;

use App\Models\ScreeningEntity;
use Illuminate\Support\Collection;

/**
 * Ask whether a name appears on a published list, and say how sure that is.
 *
 * The distinction this class exists to keep is between two claims:
 *
 *   "this name is on the World Bank's debarment list"   — checkable, and true
 *   "this Bangladeshi firm is debarred"                 — an identification
 *
 * Only the first is ever produced here. Firm names repeat across countries, and
 * a name is not a company: a match is evidence about a string, and turning it
 * into a statement about an organisation is a judgement a person makes with the
 * two records in front of them.
 */
class EntityScreener
{
    /**
     * Token overlap below which a pair is not worth a reviewer's time.
     *
     * Two names sharing one common word — "national", "construction" — overlap
     * enough to be scored and nowhere near enough to be worth reading.
     */
    private const MINIMUM_OVERLAP = 0.6;

    public function __construct(private readonly EntityNameNormaliser $normaliser) {}

    /**
     * @return Collection<int, array{entity: ScreeningEntity, score: float, method: string}>
     */
    public function candidates(string $name, int $limit = 10): Collection
    {
        $normalised = $this->normaliser->normalise($name);

        if ($normalised === '') {
            return collect();
        }

        // Built by hand rather than mapped: a closure returning literals gives
        // the collection a narrower type than the one this method promises.
        $exact = [];

        foreach (ScreeningEntity::query()->where('normalized_name', $normalised)->limit($limit)->get() as $entity) {
            $score = 1.0;
            $exact[] = ['entity' => $entity, 'score' => $score, 'method' => 'exact-normalised-name'];
        }

        if ($exact !== []) {
            return collect($exact);
        }

        return $this->overlapping($normalised, $limit);
    }

    /**
     * Names sharing most of their distinguishing words.
     *
     * Compared in memory over a candidate set narrowed by a shared token: the
     * lists are tens of thousands of rows, and a scan over all of them for every
     * query would make screening something nobody runs.
     *
     * @return Collection<int, array{entity: ScreeningEntity, score: float, method: string}>
     */
    private function overlapping(string $normalised, int $limit): Collection
    {
        $tokens = array_values(array_filter(explode(' ', $normalised)));

        if ($tokens === []) {
            return collect();
        }

        $query = ScreeningEntity::query();

        $query->where(function ($builder) use ($tokens): void {
            foreach ($tokens as $token) {
                if (mb_strlen($token) >= 4) {
                    $builder->orWhere('normalized_name', 'like', '%'.$token.'%');
                }
            }
        });

        $scored = [];

        foreach ($query->limit(500)->get() as $entity) {
            $score = $this->overlap($tokens, array_values(array_filter(explode(' ', (string) $entity->normalized_name))));

            if ($score >= self::MINIMUM_OVERLAP) {
                $scored[] = ['entity' => $entity, 'score' => round($score, 4), 'method' => 'token-overlap'];
            }
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return collect(array_slice($scored, 0, $limit));
    }

    /**
     * @param  list<string>  $left
     * @param  list<string>  $right
     */
    private function overlap(array $left, array $right): float
    {
        if ($left === [] || $right === []) {
            return 0.0;
        }

        $shared = count(array_intersect($left, $right));
        $union = count(array_unique(array_merge($left, $right)));

        // Shared over union, not over the shorter name. Dividing by the shorter
        // name scores a subset as a perfect match — "Rahman Construction Works"
        // inside "Rahman Construction and Engineering Works" came back at 1.0,
        // indistinguishable from an exact hit, when the extra word is precisely
        // what a reviewer needs to weigh. A subset now scores 0.75: still well
        // above the threshold, still clearly not identical.
        return $union === 0 ? 0.0 : $shared / $union;
    }
}
