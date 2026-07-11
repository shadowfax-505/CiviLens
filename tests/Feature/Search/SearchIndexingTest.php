<?php

use App\Contracts\Search\Searchable;
use App\Models\Project;
use App\Models\SearchIndex;
use App\Models\User;
use App\Services\Search\SearchIndexingService;
use App\Services\Search\SearchManager;
use App\Services\Search\SearchRegistry;
use App\Support\Search\SearchQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('indexes searchable entities through a provider independent manager', function (): void {
    $admin = searchAdminUser();
    $project = Project::factory()->create([
        'project_code' => 'SEARCH-BRIDGE-001',
        'name' => 'River Bridge Upgrade',
        'description' => 'Bridge replacement and approach road improvement.',
        'is_public' => true,
        'is_active' => true,
    ]);

    expect($project)->toBeInstanceOf(Searchable::class);

    app(SearchIndexingService::class)->index($project);

    $results = app(SearchManager::class)->search(SearchQuery::fromArray([
        'q' => 'River Bridge',
        'module' => 'projects',
        'sort' => 'relevance',
        'per_page' => 10,
    ]), $admin);

    expect($results->total())->toBe(1)
        ->and($results->items()[0]->title)->toBe('River Bridge Upgrade')
        ->and(SearchIndex::query()->where('searchable_type', Project::class)->where('searchable_id', $project->id)->exists())->toBeTrue();
});

it('supports filtering sorting pagination and permission aware results', function (): void {
    $admin = searchAdminUser();
    $citizen = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);

    app(SearchIndexingService::class)->index(Project::factory()->create(['name' => 'Alpha Drainage Project', 'is_public' => true]));
    app(SearchIndexingService::class)->index(Project::factory()->create(['name' => 'Zulu Drainage Project', 'is_public' => true]));

    $adminResults = app(SearchManager::class)->search(SearchQuery::fromArray([
        'q' => 'Drainage',
        'module' => 'projects',
        'sort' => 'title',
        'direction' => 'desc',
        'per_page' => 1,
    ]), $admin);

    $citizenResults = app(SearchManager::class)->search(SearchQuery::fromArray([
        'q' => 'Drainage',
        'module' => 'projects',
    ]), $citizen);

    expect($adminResults->total())->toBe(2)
        ->and($adminResults->items()[0]->title)->toBe('Zulu Drainage Project')
        ->and($citizenResults->total())->toBe(0);
});

it('registers existing civic modules without hardcoding providers in controllers', function (): void {
    $modules = app(SearchRegistry::class)->modules();

    expect($modules)->toContain('projects')
        ->and($modules)->toContain('budgets')
        ->and($modules)->toContain('procurement')
        ->and($modules)->toContain('contractors')
        ->and($modules)->toContain('documents')
        ->and($modules)->toContain('agencies')
        ->and($modules)->toContain('geography');
});
