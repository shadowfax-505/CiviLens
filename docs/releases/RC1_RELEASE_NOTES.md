# CivicLens v1.0.0-RC1 Release Notes

## Highlights

- CivicLens v1.0 is feature complete for RC1 validation.
- Production deployment scaffold includes Docker, Nginx, PHP-FPM, Supervisor, MySQL, Redis, queue workers, scheduler loop, and production PHP settings.
- RC1 local production-style validation exercised the Docker image with MySQL 8.4, Redis 7.4, Nginx, PHP-FPM, supervised workers, scheduler, health/version/metrics endpoints, authentication, search, analytics, procurement, public portal, queue processing, and an integrity engine run.
- The Executive Command Center provides authenticated operational visibility across projects, budgets, procurement, contractors, citizen reports, documents, analytics, search, and integrity runs.
- The Civic Integrity Engine remains deterministic, evidence-backed, reproducible, and human-reviewed.
- Rule management is audited through `intelligence_rule_audits`.
- Reports support CSV, spreadsheet-compatible, and lightweight PDF downloads.
- Public-safe `/healthz`, deploy-safe `/version`, authorized `/admin/system/metrics`, request correlation, and defensive security headers support operations.
- Production Nginx now serves immutable public assets from the same release artifact as PHP-FPM, preventing Vite manifest and edge asset drift.
- Figma-guided Blade polish improves the shared app shell and authentication flows without adding a new frontend framework.

## Architecture

RC1 preserves the accepted modular Laravel monolith architecture. Domain source facts remain in operational tables. Analytics, reports, search indexes, integrity indicators, and run history are derived or review records, not replacements for source modules.

No new ADR is required for RC1 because the release hardening stays within accepted decisions:

- Laravel application framework.
- MySQL source of truth.
- Modular monolith boundaries.
- Custom role/permission foundation.
- Provider-agnostic search architecture.
- V2 separation for advanced AI, OCR, semantic search, vector search, and autonomous agents.

## Performance

- Dashboards are aggregate-first and bounded by limited rankings/latest-record queries.
- Search remains provider-agnostic with a database-backed provider for RC1.
- Report downloads render from stored `analytics_reports.payload`.
- Civic Integrity Engine runs snapshot thresholds once and execute deterministic rule services.
- Analytics metric caching stores portable arrays instead of serialized value objects to avoid persistent-cache deserialization failures across optimized deployments.
- The production Nginx image stage copies `public` from the PHP-FPM build output, so generated Vite assets remain consistent across app-rendered HTML and edge-served files.
- High-volume deployments should move heavy scheduled analysis and exports to dedicated queue capacity before increasing record cardinality.

## Security

- Authentication, CSRF, validation, policies, and role/permission checks protect administrative workflows.
- Public health/version responses are secret-safe.
- `/admin/system/metrics` requires authorization.
- `X-Request-Id` is returned on responses and attached to log context.
- Defensive headers are attached globally: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, and production HSTS.
- Nginx is aligned as the edge source for production security headers and hides duplicate upstream header values.
- Integrity indicators never accuse people or organizations and always require human review.

## Testing

Required RC gates:

- Composer validation.
- Composer audit.
- Pest feature/unit suite.
- Pint formatting.
- PHPStan analysis.
- Rector dry-run.
- Vite production build.
- Playwright browser suite.
- Laravel config, route, and view cache checks.
- Git whitespace check.

## Breaking Changes

No intentional breaking application changes are introduced in RC1.

## Migration Notes

- Set `APP_VERSION=v1.0.0-RC1`.
- Set `APP_COMMIT` to the deployed commit SHA or release artifact identifier.
- Run all pending migrations before caching routes/config/views.
- RC1 requires PHP `^8.4.1`; the production image uses PHP 8.4 and includes the Redis extension.
- Verify `civic_intelligence_runs` and `intelligence_rule_audits` retention expectations before production data import.

## Known Limitations

- Dedicated Sanctum `/api/*` token endpoints remain planned and are not part of RC1.
- Search uses the database provider until Meilisearch/OpenSearch infrastructure is configured behind `SearchProvider`.
- Report exports use lightweight built-in renderers.
- Browser testing requires Chromium binaries in CI/developer environments.
- External production deployment still requires a real HTTPS host, DNS, durable storage, backup plan, and monitoring provider; the local Docker run validates runtime readiness but is not public production hosting.
- Advanced AI, OCR, embeddings, vector databases, semantic search, machine learning, recommendation systems, and autonomous agents are explicitly excluded from v1.

## Future Roadmap

- Complete production hosting setup, backups, restore rehearsal, and deployment runbook validation.
- Add detailed OpenAPI schemas after API authentication is installed.
- Configure external search infrastructure behind the existing provider contract when production needs it.
- Promote OCR, LLMs, embeddings, semantic search, and AI-agent workflows only through ADR-backed v2 planning.
