# ADR-008: Provider-Agnostic Universal Search Platform

## Status

Accepted

## Context

CivicLens search is the primary discovery layer across projects, budgets, procurement, contractors, agencies, geography, and documents. Earlier v1 planning selected Laravel Scout and Meilisearch for keyword search, but Sprint 08 requires business logic to remain independent of Scout, Meilisearch, OpenSearch, Elasticsearch, SQL full-text, and future semantic providers.

Future modules and AI indexing workflows need to register searchable records without modifying the core search engine each time a domain is added.

## Decision

Introduce a provider-agnostic search platform built around:

- `App\Contracts\Search\Searchable`, implemented by searchable domain models.
- `App\Contracts\Search\SearchProvider`, implemented initially by `DatabaseSearchProvider`.
- `SearchManager` as the only search entry point for controllers and UI.
- `SearchRegistry` as a config-driven registry for searchable modules.
- Normalized search index, history, saved search, click, popularity, synonym, keyword, and job tables.

Laravel Scout, Meilisearch, OpenSearch, and future semantic/vector providers remain interchangeable future providers behind `SearchProvider`. Business modules must not call search-engine clients directly.

## Consequences

Benefits:

- Search providers can be replaced without rewriting controllers or business modules.
- New modules integrate by implementing `Searchable` and registering in config.
- Database-backed search provides a reliable local fallback and testable baseline.
- Search analytics, saved searches, suggestions, and knowledge graph traversal are available before external search infrastructure is configured.

Tradeoffs:

- The database provider is intentionally less powerful than Meilisearch/OpenSearch for typo tolerance and large-scale ranking.
- Indexed search rows duplicate a small amount of display/index metadata, but source domain tables remain authoritative.
- Future provider implementations must preserve the same authorization and result contracts.

## V2 Impact

This enables v2 semantic search, OCR-derived document indexing, embeddings, vector search, and AI retrieval without changing existing module controllers. AI-generated search metadata must remain separate from source records and reviewed before promotion into production workflow state.
