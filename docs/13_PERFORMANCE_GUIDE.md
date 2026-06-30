# 13 Performance Guide

## V1 Priorities

- Avoid N+1 queries.
- Use pagination for large lists.
- Add foreign key indexes.
- Cache dashboard aggregates where safe.
- Queue expensive indexing and document tasks.

## Current Tooling

- `composer metrics` reports cyclomatic complexity hotspots with `CIVICLENS_COMPLEXITY_THRESHOLD`, defaulting to 10.
- Query-heavy listing services should continue to eager load required relationships and expose reusable query builders for future Scout/Meilisearch adoption.
- Dashboard aggregate caching should be added only when correctness boundaries are clear and invalidation is documented.

## Queue Readiness

The platform now includes placeholder-safe queued jobs for notifications, reports, exports, OCR, AI processing, and search indexing. Existing business workflows remain synchronous unless a future sprint explicitly moves work behind queues.

## Search Performance

Sprint 08 introduces indexed search tables and cache-backed suggestions/analytics.

Current safeguards:

- Search provider calls paginate results.
- Search indexes include source, module, visibility, status, keyword, history, click, and queue lookup indexes.
- `SearchManager` records latency for every search execution.
- Suggestions and analytics summaries are cached through Laravel Cache.
- Indexing is queue-ready through `IndexSearchableEntity` and `ReindexSearchRegistry`.

Future external providers must preserve permission-aware filtering and should keep database filters authoritative for access control.

## V2 Expansion Notes

Add load testing, read replicas, materialized aggregates, and dataset partitioning guidance.
