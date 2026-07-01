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

Sprint 10 adds bounded intelligence rule execution and processing readiness queues. Rule execution should use module filters and limits, indicators should be indexed through the existing search provider abstraction, and dashboard counts should be cached only when invalidation is clear.

Sprint 11 public pages use paginated listings and eager-loaded public-safe relationships. Public dashboard counts should stay simple source-table aggregates until traffic justifies cached materialized summaries with explicit invalidation.

Sprint 12 procurement pages eager load tender, bid, evaluation, award, contract, payment, milestone, variation, and public award notice relationships used by each screen. Enterprise procurement metrics are calculated in a dedicated service and should be cached only after invalidation rules are defined for bid submission, award approval, variation approval, milestone completion, and contract closeout events.

## Search Performance

Sprint 08 introduces indexed search tables and cache-backed suggestions/analytics.

Current safeguards:

- Search provider calls paginate results.
- Search indexes include source, module, visibility, status, keyword, history, click, and queue lookup indexes.
- `SearchManager` records latency for every search execution.
- Suggestions and analytics summaries are cached through Laravel Cache.
- Indexing is queue-ready through `IndexSearchableEntity` and `ReindexSearchRegistry`.

Future external providers must preserve permission-aware filtering and should keep database filters authoritative for access control.

## Analytics Performance

Sprint 09 metric calculations are centralized in `MetricEngine` and cached for short windows using filter-aware cache keys. `AggregationEngine` keeps source-record filters reusable so dashboards, reports, snapshots, and alerts do not duplicate query logic.

Snapshots store point-in-time calculated payloads for reporting speed and historical reproducibility. They do not duplicate operational source facts.

Future high-volume deployments may add materialized aggregate tables, scheduled refreshes, and read replicas once production data volume justifies them.

## V2 Expansion Notes

Add load testing, read replicas, materialized aggregates, and dataset partitioning guidance.
