<?php

use App\Models\District;
use App\Models\Document;
use App\Models\DocumentStatus;
use App\Models\DocumentVisibility;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('presents district exploration as the primary public action', function (): void {
    District::factory()->create(['name' => 'Dhaka']);

    $this->get(route('public.home'))
        ->assertOk()
        ->assertSee('Explore my district')
        ->assertSee('Recently indexed')
        ->assertSee('data-district-explorer', false)
        ->assertSee('data-role-shell="public"', false);
});

it('shows only public records in the recently indexed timeline', function (): void {
    $publicVisibility = DocumentVisibility::factory()->create(['name' => 'Public', 'slug' => 'public']);
    $privateVisibility = DocumentVisibility::factory()->create(['name' => 'Internal', 'slug' => 'internal']);
    $status = DocumentStatus::factory()->create(['name' => 'Active', 'slug' => 'active']);

    Document::factory()->create([
        'title' => 'Published budget circular',
        'document_visibility_id' => $publicVisibility->id,
        'document_status_id' => $status->id,
    ]);
    Document::factory()->create([
        'title' => 'Private audit working paper',
        'document_visibility_id' => $privateVisibility->id,
        'document_status_id' => $status->id,
    ]);

    $this->get(route('public.home', ['timeline_type' => 'document']))
        ->assertOk()
        ->assertSee('Published budget circular')
        ->assertDontSee('Private audit working paper');
});

it('resolves a browser position without persisting coordinates', function (): void {
    $district = District::factory()->create([
        'name' => 'Dhaka',
        'latitude' => 23.8103,
        'longitude' => 90.4125,
        'geojson' => [
            'type' => 'Polygon',
            'coordinates' => [[[90.30, 23.70], [90.52, 23.70], [90.52, 23.92], [90.30, 23.92], [90.30, 23.70]]],
        ],
    ]);

    $this->postJson(route('public.district.resolve'), [
        'latitude' => 23.81,
        'longitude' => 90.41,
    ])->assertOk()
        ->assertJsonPath('district.id', $district->id)
        ->assertJsonPath('district.name', 'Dhaka')
        ->assertJsonPath('url', route('public.projects.index', ['district_id' => $district->id]))
        ->assertHeader('Cache-Control', 'no-store, private');

    expect($district->fresh()->latitude)->toBe('23.8103000')
        ->and($district->fresh()->longitude)->toBe('90.4125000');
});

it('filters the public project explorer by district without exposing private projects', function (): void {
    $dhaka = District::factory()->create(['name' => 'Dhaka']);
    $khulna = District::factory()->create(['name' => 'Khulna']);

    Project::factory()->create(['name' => 'Dhaka public bridge', 'district_id' => $dhaka->id, 'is_public' => true, 'is_active' => true]);
    Project::factory()->create(['name' => 'Khulna public road', 'district_id' => $khulna->id, 'is_public' => true, 'is_active' => true]);
    Project::factory()->create(['name' => 'Dhaka internal plan', 'district_id' => $dhaka->id, 'is_public' => false, 'is_active' => true]);

    $this->get(route('public.projects.index', ['district_id' => $dhaka->id]))
        ->assertOk()
        ->assertSee('Dhaka public bridge')
        ->assertDontSee('Khulna public road')
        ->assertDontSee('Dhaka internal plan');
});
