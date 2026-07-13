# CivicLens v1.0.0 Release Checklist

## Deployment

- [ ] Build release artifact from the v1.0.0 branch.
- [ ] Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_VERSION=v1.0.0`, and `APP_COMMIT` to the deployed Git SHA.
- [ ] Inject production secrets through the hosting environment.
- [ ] Run `composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader`.
- [ ] Run `npm ci` and `npm run build`.
- [ ] Run controlled migrations.
- [ ] Run `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache`.
- [ ] Restart the dedicated worker and scheduler roles after deployment.

## Rollback

- [ ] Previous container image or release artifact is available.
- [ ] Latest verified database backup is available.
- [ ] Migration rollback plan has been reviewed.
- [ ] `php artisan optimize:clear` is ready for rollback cache reset.
- [ ] Worker and scheduler restart procedure is documented.

## Verification

- [ ] `composer validate --strict` passes.
- [ ] `composer audit` passes or any advisories are documented with accepted risk.
- [ ] `php artisan test` passes.
- [ ] `vendor/bin/pint --test` passes.
- [ ] `vendor/bin/phpstan analyse` passes.
- [ ] `vendor/bin/rector process --dry-run` passes.
- [ ] `npm run build` passes.
- [ ] `npm run test:e2e` passes.
- [ ] `git diff --check` passes.

## Smoke Tests

- [ ] `/healthz` returns public-safe readiness checks.
- [ ] `/version` returns `v1.0.0` and deploy metadata without secrets.
- [ ] `/dashboard` renders for authenticated users.
- [ ] `/admin/intelligence` renders for authorized administrators.
- [ ] `/admin/intelligence/rules` renders and dry-runs rules.
- [ ] `/admin/analytics/reports` renders report generation controls.
- [ ] Public portal home, project listing, search, and report tracking routes render.
- [ ] Login, logout, registration, password reset, and profile flows work.
- [ ] Login and registration screens load the release CSS from `public/build` with no horizontal overflow on desktop or mobile.

## Database

- [ ] Migrations run from an empty database.
- [ ] Seeders complete without duplicate key failures.
- [ ] Foreign keys, indexes, and immutable audit tables are preserved.
- [ ] `civic_intelligence_runs` and `intelligence_rule_audits` are append-only in application behavior.

## Queues

- [ ] Default queue worker is supervised.
- [ ] Failed jobs are visible through the failed-jobs table.
- [ ] Worker restart command is part of deployment.
- [ ] Long-running reports, snapshots, search indexing, and processing jobs remain queue-ready.

## Scheduler

- [ ] Scheduler loop is supervised.
- [ ] `civiclens:integrity-run` is scheduled daily.
- [ ] Scheduled jobs are idempotent or append-only.
- [ ] Scheduler failures are logged.

## Redis

- [ ] Redis service is reachable in production.
- [ ] Cache store is configured for Redis.
- [ ] Queue/database fallback behavior is documented for local testing.

## Health And Metrics

- [ ] `/healthz` checks app, database, cache, storage, queue, scheduler, and integrity state.
- [ ] `/version` exposes deploy-safe app metadata only.
- [ ] `/admin/system/metrics` is authorization-protected.
- [ ] `X-Request-Id` is returned on responses.
- [ ] Defensive security headers are present.

## Logs

- [ ] Structured log channel is configured.
- [ ] Request IDs appear in log context.
- [ ] Queue failures and scheduler failures are monitored.
- [ ] Logs do not contain passwords, tokens, secrets, internal storage paths, or raw environment dumps.

## Backups

- [ ] MySQL backup schedule exists.
- [ ] Storage backup schedule exists.
- [ ] Backup retention is documented.
- [ ] Backup restore has been tested against a staging environment.

## Restore

- [ ] Database restore procedure is documented.
- [ ] Storage restore procedure is documented.
- [ ] Restore validation includes login, dashboard, document download, search, reports, and integrity dashboards.

## Known Risks

- [ ] Sanctum token APIs are not installed; v1.0.0 ships web routes and selected JSON web endpoints only.
- [ ] Search provider is database-backed until external search infrastructure is configured.
- [ ] Report exports use lightweight built-in renderers; richer XLSX/PDF packages remain optional future hardening.
- [ ] Browser tests require Chromium installation in CI or developer machines.
- [ ] Public production hosting, HTTPS, DNS, durable storage, backups, and external monitoring still require provider credentials and target-environment validation.
- [ ] No OCR, LLM, embeddings, vector database, semantic search, or AI-agent infrastructure is included in v1.0.0.
