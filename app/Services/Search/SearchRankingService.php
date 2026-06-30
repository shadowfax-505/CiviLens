<?php

namespace App\Services\Search;

use App\Models\SearchIndex;
use App\Models\SearchPopularity;
use Carbon\CarbonInterface;

class SearchRankingService
{
    public function score(SearchIndex $index, string $query): float
    {
        if ($query === '') {
            return $this->popularityScore($index);
        }

        $needle = mb_strtolower($query);
        $title = mb_strtolower($index->title);
        $description = mb_strtolower((string) $index->description);
        $text = mb_strtolower((string) $index->search_text);
        $score = 0.0;

        if ($title === $needle) {
            $score += 120;
        }

        if (str_starts_with($title, $needle)) {
            $score += 80;
        }

        if (str_contains($title, $needle)) {
            $score += 60;
        }

        if (str_contains($description, $needle)) {
            $score += 25;
        }

        if (str_contains($text, $needle)) {
            $score += 15;
        }

        $score += $this->popularityScore($index);

        $indexedAt = $index->getAttribute('indexed_at');

        if ($indexedAt instanceof CarbonInterface && $indexedAt->isAfter(now()->subDays(30))) {
            $score += 5;
        }

        return $score;
    }

    private function popularityScore(SearchIndex $index): float
    {
        $popularity = $index->getRelationValue('popularity');

        return $popularity instanceof SearchPopularity
            ? (float) $popularity->popularity_score
            : 0.0;
    }
}
