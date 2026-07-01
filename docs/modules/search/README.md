# Universal Search & Knowledge Discovery Platform

## Responsibility

The Search platform is CivicLens infrastructure, not a business module. It provides provider-agnostic search, indexing, saved searches, history, suggestions, analytics, and knowledge graph discovery across every searchable domain.

Business modules must not depend directly on Scout, Meilisearch, OpenSearch, Elasticsearch, or SQL full-text. They integrate by implementing `App\Contracts\Search\Searchable`.

## Architecture

- `Searchable` lets each source model expose title, description, keywords, relationships, module, URL, status, visibility, and metadata.
- `SearchProvider` defines the provider contract.
- `SearchManager` is the only execution entry point for controllers and UI.
- `DatabaseSearchProvider` is the first provider and the local fallback.
- `SearchRegistry` loads searchable classes from `config/civiclens.php`.
- Future Scout, Meilisearch, and OpenSearch providers remain documented placeholders behind the same interface.

## Tables

- `search_indexes`
- `search_documents`
- `search_keywords`
- `search_synonyms`
- `search_popularity`
- `search_clicks`
- `saved_searches`
- `search_history`
- `search_jobs`

Search rows reference source records by type and ID. Source domain tables remain authoritative.

## Integrated Domains

- Projects
- Budgets
- Procurement tenders and contracts
- Contractor organizations
- Documents
- Agencies
- Countries, divisions, districts, upazilas, unions, and wards

## Features

- Global search
- Module search
- Advanced filters
- Faceted module navigation
- Sorting
- Pagination
- URL-persistent filters
- Saved searches
- Recent searches
- Suggestions and autocomplete
- Result highlighting with escaped source text
- Knowledge view and relationship explorer
- Search analytics dashboard
- JSON results and suggestion endpoints

## Authorization

Search is permission-aware. The database provider filters results through source-record policies for non-admin users. Administrators retain full operational search access.

Users must never discover records that their roles and policies do not allow them to view.

## Events And Queues

Events:

- `EntityIndexed`
- `EntityReindexed`
- `SearchExecuted`
- `SearchFailed`
- `SuggestionGenerated`
- `SavedSearchCreated`
- `AnalyticsUpdated`

Jobs:

- `IndexSearchableEntity`
- `ReindexSearchRegistry`

Indexing operations are queue-ready and should be triggered by domain lifecycle events as modules mature.

## Caching

Suggestions and analytics summaries use Laravel Cache with `SEARCH_CACHE_TTL_MINUTES`. Cache invalidation occurs when search history, click tracking, and analytics-changing operations are recorded.

## Admin UI

Implemented routes:

- `GET /admin/search`
- `GET /admin/search/advanced`
- `GET /admin/search/analytics`
- `GET /admin/search/results`
- `GET /admin/search/suggestions`
- `POST /admin/search/saved`
- `POST /admin/search/clicks`
- `GET /admin/search/knowledge/{module}/{id}`

## V2 Notes

Future providers may add typo tolerance, federated ranking, OCR text, semantic vectors, embeddings, and AI retrieval. They must preserve `SearchProvider`, `Searchable`, authorization filtering, analytics, and source-record authority.

Sprint 10 registers intelligence indicators as searchable records. Search results must remain permission-aware and should expose indicators as advisory review records, not source-of-truth facts.
