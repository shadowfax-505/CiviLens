<?php

namespace App\Services\PublicPortal;

class PublicNavigationService
{
    /**
     * @return array<int, array{label: string, route: string}>
     */
    public function links(): array
    {
        return [
            ['label' => 'Projects', 'route' => route('public.projects.index')],
            ['label' => 'Agencies', 'route' => route('public.agencies.index')],
            ['label' => 'Procurement', 'route' => route('public.procurement.index')],
            ['label' => 'Contractors', 'route' => route('public.contractors.index')],
            ['label' => 'Documents', 'route' => route('public.documents.index')],
            ['label' => 'Search', 'route' => route('public.search')],
        ];
    }
}
