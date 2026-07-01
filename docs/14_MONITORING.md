# 14 Monitoring

## V1 Metrics

- Request errors.
- Slow database queries.
- Queue failures.
- Search indexing failures.
- Login failures.
- Storage errors.
- Dashboard views.
- Metric calculations.
- Snapshot generation.
- Report generation.
- Alert triggers.
- Intelligence rule execution.
- Intelligence indicator review transitions.
- Intelligence processing job lifecycle events.

## Current Observability Layer

- Domain events are logged through a shared listener for project, budget, tender, contract, document, search, and analytics milestones.
- Sprint 09 analytics events are logged for metric calculation, dashboard view, snapshot, report, alert, insight, and cache refresh activity.
- Sprint 10 intelligence events are logged for rule execution, indicator detection, evidence linking, review transitions, and processing job queue/completion/failure states.
- Structured log context helpers keep operational metadata consistent.
- Performance timing helpers are available for future instrumentation around expensive workflows.
- Queue jobs log lifecycle activity and should be connected to queue failure monitoring in production.

## Future-Compatible Tools

Laravel Pulse and Telescope remain compatible options for local diagnostics and future production observability planning. Do not install heavy production monitoring tools without a dedicated operations decision.

## V2 Expansion Notes

Add public API telemetry, model inference logs, and data freshness dashboards.
