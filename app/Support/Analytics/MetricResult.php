<?php

namespace App\Support\Analytics;

class MetricResult
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $category,
        public readonly int|float|string $value,
        public readonly ?string $unit = null,
        public readonly ?string $description = null,
        public readonly array $meta = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'category' => $this->category,
            'value' => $this->value,
            'unit' => $this->unit,
            'description' => $this->description,
            'meta' => $this->meta,
        ];
    }
}
