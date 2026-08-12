<?php

use App\Models\Role;
use App\Models\SourcePublisher;
use App\Models\TenderObservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function findingsAdmin(): User
{
    $role = Role::query()->create(['name' => 'Administrator', 'slug' => 'admin']);
    $admin = User::factory()->create(['email' => 'findings-admin@example.com']);
    $admin->roles()->attach($role);

    return $admin;
}

function findingsPublisher(): SourcePublisher
{
    return SourcePublisher::query()->create([
        'slug' => 'bppa-egp',
        'name' => 'Bangladesh Public Procurement Authority',
        'source_class' => 'government',
        'canonical_url' => 'https://www.eprocure.gov.bd/',
        'attribution_name' => 'Bangladesh Public Procurement Authority',
        'is_active' => true,
        'metadata' => [],
    ]);
}

function findingsObservation(SourcePublisher $publisher, string $id, string $status, string $at): void
{
    TenderObservation::query()->create([
        'source_publisher_id' => $publisher->getKey(),
        'external_id' => $id,
        'status' => $status,
        'observed_at' => $at,
        'observation_hash' => hash('sha256', $id.$status.$at),
    ]);
}

it('keeps findings behind the same authorization as the registry', function (): void {
    $publisher = findingsPublisher();
    findingsObservation($publisher, '1302100', 'Live', '2026-08-11 02:00:06');

    $this->actingAs(User::factory()->create())->get('/admin/sources/findings')->assertForbidden();
});

it('shows what publishers declared and what they later changed', function (): void {
    $publisher = findingsPublisher();
    findingsObservation($publisher, '1302100', 'Live', '2026-08-11 02:00:06');
    findingsObservation($publisher, '1302100', 'Being processed', '2026-08-11 12:00:24');
    findingsObservation($publisher, '1309032', 'Amendment/Corrigendum issued : 2', '2026-08-11 02:00:06');

    $response = $this->actingAs(findingsAdmin())->get('/admin/sources/findings');

    $response->assertOk()
        ->assertSee('Bangladesh Public Procurement Authority')
        ->assertSee('2 notices observed')
        ->assertSee('1302100')
        // The page reports counts and never characterises them.
        ->assertSee('not that anything was wrong', false)
        ->assertDontSee('suspicious')
        ->assertDontSee('irregular');
});

it('says nothing has been observed rather than showing a page of zeroes', function (): void {
    // A publisher with no observations would otherwise render as "0 amended,
    // 0 revised", which reads as a finding about that publisher rather than an
    // absence of data.
    findingsPublisher();

    $this->actingAs(findingsAdmin())->get('/admin/sources/findings')
        ->assertOk()
        ->assertSee('Nothing observed yet');
});
