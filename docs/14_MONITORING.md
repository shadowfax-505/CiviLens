# 14 Monitoring

## V1 Metrics

- Request errors.
- Slow database queries.
- Queue failures.
- Search indexing failures.
- Login failures.
- Storage errors.

## Current Observability Layer

- Domain events are logged through a shared listener for project, budget, tender, contract, and document milestones.
- Structured log context helpers keep operational metadata consistent.
- Performance timing helpers are available for future instrumentation around expensive workflows.
- Queue jobs log lifecycle activity and should be connected to queue failure monitoring in production.

## Future-Compatible Tools

Laravel Pulse and Telescope remain compatible options for local diagnostics and future production observability planning. Do not install heavy production monitoring tools without a dedicated operations decision.

## V2 Expansion Notes

Add public API telemetry, model inference logs, and data freshness dashboards.
