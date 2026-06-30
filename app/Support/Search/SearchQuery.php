<?php

namespace App\Support\Search;

class SearchQuery
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public readonly string $query = '',
        public readonly ?string $module = null,
        public readonly array $filters = [],
        public readonly string $sort = 'relevance',
        public readonly string $direction = 'desc',
        public readonly int $page = 1,
        public readonly int $perPage = 15,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $filters = $input['filters'] ?? [];
        $filters = is_array($filters) ? $filters : [];

        foreach (['status', 'visibility', 'date_from', 'date_to', 'numeric_min', 'numeric_max'] as $filter) {
            if (array_key_exists($filter, $input) && $input[$filter] !== null && $input[$filter] !== '') {
                $filters[$filter] = $input[$filter];
            }
        }

        $direction = strtolower((string) ($input['direction'] ?? 'desc'));

        return new self(
            query: trim((string) ($input['q'] ?? $input['query'] ?? '')),
            module: filled($input['module'] ?? null) ? (string) $input['module'] : null,
            filters: $filters,
            sort: (string) ($input['sort'] ?? 'relevance'),
            direction: in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc',
            page: max(1, (int) ($input['page'] ?? 1)),
            perPage: min(100, max(1, (int) ($input['per_page'] ?? $input['perPage'] ?? 15))),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'q' => $this->query,
            'module' => $this->module,
            'filters' => $this->filters,
            'sort' => $this->sort,
            'direction' => $this->direction,
            'page' => $this->page,
            'per_page' => $this->perPage,
        ];
    }
}
