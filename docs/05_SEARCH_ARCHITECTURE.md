# 05 Search Architecture

## Search Goals

Universal Search is the primary discovery layer of CivicLens. A user should be able to search once and discover related projects, budgets, procurement records, contractors, agencies, geography, and documents.

Search must understand relationships and must remain independent of any specific provider.

## Provider-Agnostic Architecture

Sprint 08 introduces the provider abstraction documented in `docs/adr/ADR-008-Provider-Agnostic-Universal-Search.md`.

Core contracts:

- `App\Contracts\Search\Searchable`
- `App\Contracts\Search\SearchProvider`

Core services:

- `SearchManager`
- `SearchRegistry`
- `DatabaseSearchProvider`
- `SearchIndexingService`
- `SearchRankingService`
- `SearchAnalyticsService`
- `SearchSuggestionService`
- `KnowledgeGraphService`

Controllers and business modules must use `SearchManager`, `SearchIndexingService`, or `KnowledgeGraphService`. They must never call Meilisearch, Scout, OpenSearch, Elasticsearch, or SQL full-text provider clients directly.

## Current Provider

`DatabaseSearchProvider` is the v1 baseline provider. It supports:

- Keyword and phrase matching.
- Module filtering.
- Status and visibility filtering.
- Relevance, title, module, and status sorting.
- Pagination.
- Suggestions from indexed keywords.
- Permission-aware result filtering.
- Escaped result highlighting.

## Future Providers

Future providers must implement `SearchProvider`.

Documented placeholders:

- `FutureScoutProvider`
- `FutureMeilisearchProvider`
- `FutureOpenSearchProvider`

External providers must preserve authorization, analytics, saved search, history, click tracking, cache, and result DTO behavior.

## Searchable Modules

Searchable records register through `config/civiclens.php`.

Initial searchable domains:

- Projects
- Budgets
- Procurement tenders and contracts
- Contractor organizations
- Documents
- Agencies
- Countries, divisions, districts, upazilas, unions, and wards

Future modules integrate by implementing `Searchable` and registering their class in config.

## Knowledge Graph

`KnowledgeGraphService` exposes related entities for a source record without hardcoding graph traversal inside controllers.

Initial graph relationships include:

- Project -> agency, budgets, tenders, documents.
- Budget -> project, tenders, documents.
- Tender -> project, budget, agency, documents.
- Agency -> projects, tenders, documents.
- Document -> attached source records.

## Analytics And Cache

Search analytics track history, failed searches, saved searches, click-throughs, latency, and top terms.

Cached data:

- Suggestions.
- Analytics summaries.

Cache invalidation occurs through analytics writes and click tracking. The TTL is controlled by `SEARCH_CACHE_TTL_MINUTES`.

Sprint 13 part 2 surfaces search activity in the Executive Command Center and authorized system metrics. These summaries read the existing search analytics and search job tables; they do not introduce semantic search, embeddings, vector databases, or provider-specific query code.

## Queueing

Indexing is queue-ready through:

- `IndexSearchableEntity`
- `ReindexSearchRegistry`

Domain lifecycle events should queue indexing operations as modules mature. Long-running OCR, AI, and semantic indexing must not block request lifecycles.

## V2 Expansion Notes

Add OCR text, semantic search, embeddings, vector search, typo tolerance, and federated external search providers behind `SearchProvider`. AI outputs must remain separate from source facts and reviewed before becoming workflow state.
