<?php

namespace App\Services\Search;

use App\Contracts\Search\Searchable;
use Illuminate\Database\Eloquent\Model;

class SearchRegistry
{
    /**
     * @return array<string, class-string<Model&Searchable>>
     */
    public function searchableClasses(): array
    {
        $classes = config('civiclens.search.registry', []);

        return collect(is_array($classes) ? $classes : [])
            ->filter(fn (mixed $class): bool => is_string($class) && is_subclass_of($class, Model::class) && is_subclass_of($class, Searchable::class))
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function modules(): array
    {
        return collect($this->searchableClasses())
            ->keys()
            ->map(fn (string $module): string => $this->moduleGroup($module))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return class-string<Model&Searchable>|null
     */
    public function classForModule(string $module): ?string
    {
        $classes = $this->searchableClasses();

        return $classes[$module] ?? null;
    }

    public function moduleGroup(string $module): string
    {
        return match ($module) {
            'tenders', 'contracts' => 'procurement',
            'organizations', 'contractor_profiles' => 'contractors',
            'countries', 'divisions', 'districts', 'upazilas', 'unions', 'wards' => 'geography',
            default => $module,
        };
    }
}
