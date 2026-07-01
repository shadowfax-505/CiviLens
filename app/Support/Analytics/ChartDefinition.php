<?php

namespace App\Support\Analytics;

class ChartDefinition
{
    /**
     * @param  list<string>  $labels
     * @param  list<array<string, mixed>>  $datasets
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly string $type,
        public readonly array $labels,
        public readonly array $datasets,
        public readonly array $options = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'type' => $this->type,
            'data' => [
                'labels' => $this->labels,
                'datasets' => $this->datasets,
            ],
            'options' => $this->options,
        ];
    }
}
