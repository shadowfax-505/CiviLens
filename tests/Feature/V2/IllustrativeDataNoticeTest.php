<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The domain tables hold seeded rows — "Mirpur Water Resilience Package" under
 * a "Ministry of Planning" — written to develop against. On a site about
 * Bangladeshi public spending a reader has no way to tell them from real
 * records, and publishing them unmarked would be publishing fabricated
 * government data.
 */
it('marks every page served from the domain tables as not real', function (string $route): void {
    $this->get($route)
        ->assertOk()
        ->assertSee('Nothing on this page is a real record');
})->with([
    '/public/projects',
    '/public/agencies',
    '/public/procurement',
    '/public/contractors',
    '/public/documents',
    '/public/search',
    '/',
]);

it('does not put the notice on the certified evidence pages', function (): void {
    // Those serve the published snapshot and nothing else. A warning there
    // would teach readers to ignore the one on the pages that need it.
    $this->get(route('public.evidence.index'))
        ->assertOk()
        ->assertDontSee('Nothing on this page is a real record');
});
