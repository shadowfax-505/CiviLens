<?php

namespace App\Models\Concerns;

trait SearchableGeography
{
    public function searchTitle(): string
    {
        return $this->name;
    }

    public function searchDescription(): ?string
    {
        return 'Administrative geography reference: '.$this->name;
    }

    public function searchKeywords(): array
    {
        return array_values(array_filter([
            $this->name,
            $this->code ?? null,
            $this->iso2 ?? null,
            $this->iso3 ?? null,
        ]));
    }

    public function searchRelations(): array
    {
        return [];
    }

    public function searchModule(): string
    {
        return 'geography';
    }

    public function searchUrl(): string
    {
        return route('admin.geography.'.$this->geographyRouteSegment().'.index', ['search' => $this->name], false);
    }

    public function searchStatus(): ?string
    {
        return 'active';
    }

    public function searchVisibility(): string
    {
        return 'internal';
    }

    public function searchMetadata(): array
    {
        return [
            'route_module' => $this->getTable(),
            'table' => $this->getTable(),
            'latitude' => $this->latitude ?? null,
            'longitude' => $this->longitude ?? null,
        ];
    }

    private function geographyRouteSegment(): string
    {
        return match ($this->getTable()) {
            'countries' => 'countries',
            'divisions' => 'divisions',
            'districts' => 'districts',
            'upazilas' => 'upazilas',
            'unions' => 'unions',
            default => 'wards',
        };
    }
}
