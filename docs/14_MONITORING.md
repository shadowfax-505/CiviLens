# 14 Monitoring

## V1 Metrics

- Request errors.
- Slow database queries.
- Queue failures.
- Pending queued jobs.
- Search indexing failures.
- Login failures.
- Storage errors.
- Dashboard views.
- Metric calculations.
- Snapshot generation.
- Report generation.
- Alert triggers.
- Intelligence rule execution.
- Civic integrity engine run status and indicator counts.
- Civic integrity rule execution duration and last execution timestamps.
- Intelligence indicator review transitions.
- Intelligence processing job lifecycle events.
- Request and correlation IDs.
- Release version metadata.
- Procurement plan approval, bid opening, evaluation completion, award approval, variation approval, milestone completion, and contract closeout events.
- Source endpoint health, pause state, crawl run completion/failure, quarantine volume, and ingestion queue latency.

## Current Observability Layer

- Domain events are logged through a shared listener for project, budget, tender, contract, document, search, and analytics milestones.
- Sprint 09 analytics events are logged for metric calculation, dashboard view, snapshot, report, alert, insight, and cache refresh activity.
- Sprint 10 intelligence events are logged for rule execution, indicator detection, evidence linking, review transitions, and processing job queue/completion/failure states.
- Sprint 11 public portal events are logged for citizen report submission/status/archive, public search, public document download, and public project view activity.
- Sprint 12 procurement lifecycle events are logged for plan approval, bid opening, evaluation completion, award approval, variation approval, milestone completion, and contract closeout.
- Sprint 13 adds `/healthz` for app/database/cache/storage/queue/scheduler/integrity readiness, `/version` for deploy metadata, `/admin/system/metrics` for authorized operator metrics, and `civic_intelligence_runs` for scheduled deterministic engine status.
- V2 Stage 3 adds administrator-only ingestion aggregates to `/admin/system/metrics`: active publishers, endpoints, paused/failing endpoints, recent runs/failures, and quarantined artifact count. Publisher URLs and errors remain outside public `/healthz`.
- `RequestCorrelation` attaches `X-Request-Id` to responses and structured log context.
- `SecurityHeaders` attaches baseline defensive headers to every response.
- `intelligence_rule_audits` records rule-management changes and execution events for operational review.
- Structured log context helpers keep operational metadata consistent.
- Performance timing helpers are available for future instrumentation around expensive workflows.
- Queue jobs log lifecycle activity and should be connected to queue failure monitoring in production.

## Future-Compatible Tools

Laravel Pulse and Telescope remain compatible options for local diagnostics and future production observability planning. Do not install heavy production monitoring tools without a dedicated operations decision.

## V2 Expansion Notes

Add public API telemetry, model inference logs, and data freshness dashboards.
