<?php

use App\Models\Budget;
use App\Models\Document;
use App\Models\Documentable;
use App\Models\Project;
use App\Models\Tender;
use App\Services\Search\KnowledgeGraphService;
use App\Services\Search\SearchIndexingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exposes related civic entities through the reusable knowledge graph service', function (): void {
    $project = Project::factory()->create(['name' => 'Knowledge Bridge Corridor']);
    $budget = Budget::factory()->for($project)->create();
    $tender = Tender::factory()->for($project)->for($budget)->create(['title' => 'Knowledge Bridge Tender']);
    $document = Document::factory()->create(['title' => 'Knowledge Bridge Contract File']);
    Documentable::query()->create([
        'document_id' => $document->id,
        'documentable_type' => Project::class,
        'documentable_id' => $project->id,
        'relationship_type' => 'supporting',
    ]);

    app(SearchIndexingService::class)->index($project);
    app(SearchIndexingService::class)->index($budget);
    app(SearchIndexingService::class)->index($tender);
    app(SearchIndexingService::class)->index($document);

    $graph = app(KnowledgeGraphService::class)->for($project, searchAdminUser());

    expect($graph['entity']->title)->toBe('Knowledge Bridge Corridor')
        ->and($graph['relationships']['budgets'])->toHaveCount(1)
        ->and($graph['relationships']['procurement'])->toHaveCount(1)
        ->and($graph['relationships']['documents'])->toHaveCount(1);
});

it('renders universal search and knowledge views for authorized users', function (): void {
    $admin = searchAdminUser();
    $project = Project::factory()->create(['name' => 'Universal Search Bridge']);
    app(SearchIndexingService::class)->index($project);

    $this->actingAs($admin)->get('/admin/search?q=Universal&module=projects')
        ->assertOk()
        ->assertSee('Universal Search')
        ->assertSee('Universal Search Bridge');

    $this->actingAs($admin)->get('/admin/search/knowledge/projects/'.$project->id)
        ->assertOk()
        ->assertSee('Knowledge View')
        ->assertSee('Universal Search Bridge');
});
