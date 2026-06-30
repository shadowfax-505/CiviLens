<?php

use App\Events\SavedSearchCreated;
use App\Events\SearchExecuted;
use App\Events\SuggestionGenerated;
use App\Jobs\IndexSearchableEntity;
use App\Models\Project;
use App\Models\SavedSearch;
use App\Models\SearchHistory;
use App\Services\Search\SearchAnalyticsService;
use App\Services\Search\SearchIndexingService;
use App\Services\Search\SearchManager;
use App\Services\Search\SearchSuggestionService;
use App\Support\Search\SearchQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('records search history saved searches suggestions events and queued indexing', function (): void {
    Queue::fake();
    Event::fake([SearchExecuted::class, SavedSearchCreated::class, SuggestionGenerated::class]);
    Cache::flush();

    $admin = searchAdminUser();
    $project = Project::factory()->create(['name' => 'Bridge Market Access Project']);
    app(SearchIndexingService::class)->index($project);

    app(SearchManager::class)->search(SearchQuery::fromArray(['q' => 'bridge', 'module' => 'projects']), $admin);
    app(SearchAnalyticsService::class)->saveSearch($admin, 'Bridge Watch', SearchQuery::fromArray(['q' => 'bridge', 'module' => 'projects']));
    app(SearchIndexingService::class)->queue($project);

    $suggestions = app(SearchSuggestionService::class)->suggest('bri', $admin);

    expect(SearchHistory::query()->where('query', 'bridge')->where('user_id', $admin->id)->exists())->toBeTrue()
        ->and(SavedSearch::query()->where('name', 'Bridge Watch')->where('user_id', $admin->id)->exists())->toBeTrue()
        ->and($suggestions)->toContain('bridge')
        ->and(Cache::has('search:suggestions:'.md5($admin->id.'|bri')))->toBeTrue();

    Queue::assertPushed(IndexSearchableEntity::class);
    Event::assertDispatched(SearchExecuted::class);
    Event::assertDispatched(SavedSearchCreated::class);
    Event::assertDispatched(SuggestionGenerated::class);
});
