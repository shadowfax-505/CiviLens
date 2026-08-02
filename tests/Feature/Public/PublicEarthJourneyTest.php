<?php

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps the established static hero when the Earth journey is disabled', function (): void {
    config()->set('civiclens.earth_journey.enabled', false);

    $this->get('/')
        ->assertOk()
        ->assertSee('Public delivery,')
        ->assertDontSee('data-civic-earth', false);
});

it('renders the Earth journey with real public summary values when enabled', function (): void {
    config()->set('civiclens.earth_journey.enabled', true);

    Project::factory()->create(['is_public' => true, 'is_active' => true]);
    Project::factory()->create(['is_public' => false, 'is_active' => true]);

    $this->get('/')
        ->assertOk()
        ->assertSee('data-civic-earth', false)
        ->assertSee('data-project-markers-url="'.route('public.projects.map-data').'"', false)
        ->assertSee('data-public-projects="1"', false)
        ->assertSee('data-public-tenders="0"', false)
        ->assertSee('data-public-documents="0"', false);
});
