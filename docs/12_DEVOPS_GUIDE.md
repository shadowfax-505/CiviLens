# 12 DevOps Guide

## CI/CD

V1 runs GitHub Actions on push and pull request. The workflow validates Composer metadata, installs PHP and Node dependencies with caching, prepares the Laravel test environment, runs Pest, Pint, PHPStan/Larastan, Rector dry-run, complexity metrics, the frontend build, Playwright browser tests, and Laravel cache checks.

## Static Analysis

- `composer analyse` runs PHPStan/Larastan at level 8.
- The active PHPStan scope covers controllers, requests, policies, providers, services, support classes, configuration, and routes.
- Strict/deprecation rule packages are installed for future expansion, but the active profile stays focused on reliable Laravel application checks.
- Suppressions should be avoided; if one becomes necessary, document the reason near the configuration change.

## Automated Refactoring

- `composer refactor:dry` runs Rector in dry-run mode.
- The current Rector profile is intentionally conservative and limited to safe dead-code detection.
- Do not apply broad automated rewrites to business workflows without a dedicated refactoring sprint.

## Browser Testing

- `npm run test:e2e` runs Playwright.
- CI installs Chromium before execution.
- Screenshots, videos, and traces are retained only when useful for failures.

## Operations

- Back up MySQL.
- Monitor queue failures.
- Track search indexing failures.
- Monitor `/nginx-healthz` for ingress readiness and `/healthz` for app, database, cache, storage, and queue diagnostics.
- Verify `/version` after each release and compare `APP_VERSION`/`APP_COMMIT` with the deployment artifact.
- For v1.0.0, `APP_VERSION` must be `v1.0.0`.
- Use authorized `/admin/system/metrics` for operator-facing database, cache, queue, scheduler, storage, and integrity-run summaries.
- Confirm the dedicated queue worker and scheduler roles remain healthy in production.
- Review `civic_intelligence_runs` after scheduled execution to detect failed integrity runs.
- Review `intelligence_rule_audits` after rule-threshold changes or dry-run tuning sessions.
- Record release notes.
- Restart workers after deployments.
- Keep `.env.example` aligned with queue, storage, mail, and external-service configuration.

## Production Runtime

Sprint 13 includes production container files for PHP 8.4 FPM, Nginx, MySQL, Redis, dedicated queue workers, and a scheduler. Deployments should run optimized Composer autoloading, Vite builds, controlled migrations, `php artisan optimize`, and worker restarts. Rollbacks should restore the previous image and database backup or migration rollback plan before restarting workers, then clear/rebuild Laravel caches and recheck health/version endpoints.

## V2 Expansion Notes

Add blue/green deployment notes, centralized logs, and infrastructure-as-code.
