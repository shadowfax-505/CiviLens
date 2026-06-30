# Universal Search Platform Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build CivicLens Sprint 08: a provider-agnostic universal search, knowledge graph, analytics, suggestions, saved-search, and discovery platform.

**Architecture:** Introduce `App\Contracts\Search\Searchable` so each domain controls its own index payload, while `SearchManager` talks only to a `SearchProvider` interface. The first concrete provider is `DatabaseSearchProvider`; Scout, OpenSearch, and Meilisearch remain documented placeholders behind the same contract.

**Tech Stack:** Laravel 13, Blade, Tailwind, Pest, Playwright, queued jobs, events, cache, query builder pagination.

---

## File Structure

- Create `database/migrations/2026_06_30_000013_create_universal_search_tables.php` for normalized search index, analytics, saved search, history, clicks, synonyms, keywords, and job tables.
- Create `app/Contracts/Search/Searchable.php` and `app/Contracts/Search/SearchProvider.php` for dependency inversion.
- Create `app/Support/Search/SearchQuery.php` and `app/Support/Search/SearchResult.php` as provider-neutral DTOs.
- Create `app/Services/Search/SearchManager.php`, `SearchRegistry.php`, `DatabaseSearchProvider.php`, `SearchIndexingService.php`, `SearchRankingService.php`, `SearchAnalyticsService.php`, `SearchSuggestionService.php`, and `KnowledgeGraphService.php`.
- Create `app/Models/SearchIndex.php`, `SearchDocument.php`, `SearchKeyword.php`, `SearchSynonym.php`, `SearchPopularity.php`, `SearchClick.php`, `SavedSearch.php`, `SearchHistory.php`, and `SearchJob.php`.
- Create search events and queue jobs under `app/Events` and `app/Jobs`.
- Extend existing models (`Project`, `Budget`, `Tender`, `Contract`, `Organization`, `Document`, `Agency`, and geography models) with `Searchable`.
- Create `app/Http/Controllers/Admin/SearchController.php`, `SearchAnalyticsController.php`, and `SearchKnowledgeController.php`.
- Add `resources/views/admin/search/index.blade.php`, `advanced.blade.php`, `analytics.blade.php`, and `knowledge.blade.php`.
- Update `routes/web.php`, `config/civiclens.php`, app provider bindings, navigation, tests, browser flows, and documentation.

---

### Task 1: Search Contract and Schema

**Files:**
- Create: `tests/Feature/Search/SearchSchemaTest.php`
- Create: `database/migrations/2026_06_30_000013_create_universal_search_tables.php`
- Create: `app/Contracts/Search/Searchable.php`
- Create: `app/Contracts/Search/SearchProvider.php`
- Create: `app/Support/Search/SearchQuery.php`
- Create: `app/Support/Search/SearchResult.php`

- [ ] **Step 1: Write the failing schema/contract test**

```php
it('creates normalized search platform tables and contracts', function (): void {
    expect(interface_exists(\App\Contracts\Search\Searchable::class))->toBeTrue();
    expect(interface_exists(\App\Contracts\Search\SearchProvider::class))->toBeTrue();

    foreach (['search_indexes', 'search_documents', 'search_keywords', 'search_synonyms', 'search_popularity', 'search_clicks', 'saved_searches', 'search_history', 'search_jobs'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});
```

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Feature/Search/SearchSchemaTest.php`

Expected: fail because contracts and search tables do not exist.

- [ ] **Step 3: Implement schema and contracts**

Define `Searchable` with `searchTitle()`, `searchDescription()`, `searchKeywords()`, `searchRelations()`, `searchModule()`, `searchUrl()`, `searchStatus()`, `searchVisibility()`, and `searchMetadata()`. Define `SearchProvider` with `search()`, `index()`, `delete()`, and `suggest()`.

- [ ] **Step 4: Verify GREEN**

Run: `php artisan test tests/Feature/Search/SearchSchemaTest.php`

Expected: pass.

### Task 2: Search Models, Registry, Provider, Ranking, and Indexing

**Files:**
- Create: `tests/Feature/Search/SearchIndexingTest.php`
- Create: search model classes in `app/Models`
- Create: search services in `app/Services/Search`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `config/civiclens.php`
- Modify: searchable domain models

- [ ] **Step 1: Write failing indexing and provider tests**

```php
it('indexes searchable entities through the provider independent manager', function (): void {
    $project = Project::factory()->create(['name' => 'River Bridge Upgrade']);

    app(SearchIndexingService::class)->index($project);

    $results = app(SearchManager::class)->search(SearchQuery::fromArray(['q' => 'River Bridge']), adminUser());

    expect($results->total())->toBeGreaterThan(0);
    expect($results->first()->title)->toContain('River Bridge');
});
```

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Feature/Search/SearchIndexingTest.php`

Expected: fail because models/services do not exist.

- [ ] **Step 3: Implement provider and searchable payloads**

Implement `DatabaseSearchProvider` with database-backed filtering, facets, sorting, pagination, permission filtering, ranking, highlighting, and query latency tracking. Make each supported domain model implement `Searchable`.

- [ ] **Step 4: Verify GREEN**

Run: `php artisan test tests/Feature/Search/SearchIndexingTest.php`

Expected: pass.

### Task 3: Analytics, Suggestions, Saved Searches, Cache, Events, and Queues

**Files:**
- Create: `tests/Feature/Search/SearchAnalyticsTest.php`
- Create: `app/Events/EntityIndexed.php`, `EntityReindexed.php`, `SearchExecuted.php`, `SearchFailed.php`, `SuggestionGenerated.php`, `SavedSearchCreated.php`, `AnalyticsUpdated.php`
- Create: `app/Jobs/IndexSearchableEntity.php`, `ReindexSearchRegistry.php`
- Create: analytics and suggestion services

- [ ] **Step 1: Write failing analytics test**

```php
it('records search history, suggestions, saved searches, and queued indexing', function (): void {
    Queue::fake();
    Event::fake([SearchExecuted::class, SavedSearchCreated::class, SuggestionGenerated::class]);

    $manager = app(SearchManager::class);
    $manager->search(SearchQuery::fromArray(['q' => 'bridge']), adminUser());
    app(SearchAnalyticsService::class)->saveSearch(adminUser(), 'Bridge Watch', SearchQuery::fromArray(['q' => 'bridge']));
    app(SearchIndexingService::class)->queue(Project::factory()->create());

    expect(SearchHistory::query()->where('query', 'bridge')->exists())->toBeTrue();
    expect(SavedSearch::query()->where('name', 'Bridge Watch')->exists())->toBeTrue();
    Queue::assertPushed(IndexSearchableEntity::class);
});
```

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Feature/Search/SearchAnalyticsTest.php`

Expected: fail because services/events/jobs do not exist.

- [ ] **Step 3: Implement analytics and queue hooks**

Add cache-backed suggestions/facets/analytics summaries, history writes, click tracking, saved-search persistence, and queue jobs that call `SearchIndexingService`.

- [ ] **Step 4: Verify GREEN**

Run: `php artisan test tests/Feature/Search/SearchAnalyticsTest.php`

Expected: pass.

### Task 4: Knowledge Graph and UI

**Files:**
- Create: `tests/Feature/Search/SearchInterfaceTest.php`
- Create: search controllers and views
- Modify: `routes/web.php`
- Modify: `resources/views/components/layouts/app.blade.php`

- [ ] **Step 1: Write failing UI and knowledge tests**

```php
it('renders global search, advanced search, analytics, and knowledge views', function (): void {
    $project = Project::factory()->create(['name' => 'Knowledge Bridge']);
    app(SearchIndexingService::class)->index($project);

    actingAsAdmin()
        ->get('/admin/search?q=Knowledge')
        ->assertOk()
        ->assertSee('Universal Search')
        ->assertSee('Knowledge Bridge');

    actingAsAdmin()
        ->get('/admin/search/knowledge/project/'.$project->id)
        ->assertOk()
        ->assertSee('Knowledge View');
});
```

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Feature/Search/SearchInterfaceTest.php`

Expected: fail because routes/controllers/views do not exist.

- [ ] **Step 3: Implement UI**

Create accessible Blade search pages with global search bar, advanced filters, result cards, highlights, saved searches, recent searches, suggestions, analytics summary cards, and knowledge graph relationship lists.

- [ ] **Step 4: Verify GREEN**

Run: `php artisan test tests/Feature/Search/SearchInterfaceTest.php`

Expected: pass.

### Task 5: Browser Coverage and Documentation

**Files:**
- Modify: `tests/Browser/admin-workflows.spec.ts`
- Create: `docs/modules/search/README.md`
- Modify: database, search architecture, system architecture, domain model, API, performance guide, changelog, and AI memory files.

- [ ] **Step 1: Add browser workflow**

Add a Playwright test that visits `/admin/search`, searches for seeded records, opens knowledge view, checks autocomplete/suggestions, and verifies mobile layout.

- [ ] **Step 2: Update documentation**

Document the provider abstraction, registry, normalized tables, knowledge graph, analytics, queues, events, cache, API routes, and future Scout/Meilisearch/OpenSearch providers.

- [ ] **Step 3: Run quality gate**

Run:

```bash
composer validate --strict
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse --debug --no-ansi --memory-limit=1G
vendor/bin/rector process --dry-run
npm run build
npm run test:e2e
git diff --check
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize:clear
```

Expected: all commands pass.

---

## Spec Coverage Review

- Provider independence: Tasks 1-2.
- `Searchable` contract recommendation: Tasks 1-2.
- Registry: Task 2.
- Index tables: Task 1.
- Knowledge graph: Task 4.
- Search features, saved/recent/suggestions/facets: Tasks 2-4.
- Ranking: Task 2.
- Analytics/cache: Task 3.
- Queue/events: Task 3.
- UI/browser: Tasks 4-5.
- Documentation and memory: Task 5.
