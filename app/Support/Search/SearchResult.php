<?php

namespace App\Support\Search;

use App\Models\SearchIndex;

class SearchResult
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, string>  $highlights
     */
    public function __construct(
        public readonly int $id,
        public readonly string $module,
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?string $url,
        public readonly string $searchableType,
        public readonly int $searchableId,
        public readonly float $score,
        public readonly string $visibility,
        public readonly ?string $status,
        public readonly array $metadata = [],
        public readonly array $highlights = [],
    ) {}

    public static function fromIndex(SearchIndex $index, float $score = 0.0, string $query = ''): self
    {
        $metadata = $index->metadata;
        $metadata = is_array($metadata) ? $metadata : [];

        return new self(
            id: $index->id,
            module: $index->module,
            title: $index->title,
            description: $index->description,
            url: $index->url,
            searchableType: $index->searchable_type,
            searchableId: $index->searchable_id,
            score: $score,
            visibility: $index->visibility,
            status: $index->status,
            metadata: $metadata,
            highlights: self::highlights($index, $query),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function highlights(SearchIndex $index, string $query): array
    {
        if ($query === '') {
            return [];
        }

        $highlight = static function (?string $value) use ($query): ?string {
            if ($value === null) {
                return null;
            }

            return preg_replace('/('.preg_quote(e($query), '/').')/i', '<mark>$1</mark>', e($value));
        };

        return array_filter([
            'title' => $highlight($index->title),
            'description' => $highlight($index->description),
        ]);
    }
}
