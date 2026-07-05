# 11 Deployment Guide

## V1 Local Environment

Use Docker or Laravel Sail for PHP, MySQL, Redis, Meilisearch, and mail testing.

## Production Container Stack

Sprint 13 part 1 adds a production-oriented Docker scaffold:

- `Dockerfile` builds Composer dependencies, Vite assets, a PHP 8.4 PHP-FPM runtime with the Redis extension, and an Nginx runtime stage with the same immutable public assets.
- `docker-compose.production.yml` defines app, worker, image-backed Nginx, MySQL, and Redis services.
- `docker/production/nginx.conf` serves public assets from the release image and forwards PHP requests to PHP-FPM.
- `docker/production/supervisord.conf` runs queue workers and the Laravel scheduler loop.
- `docker/production/php.ini` sets production PHP limits and disables error display.
- `.env.production.example` documents required production environment variables.

Use this scaffold as a deployable baseline. Production secrets must be injected through the hosting environment, not committed.

## Deployment Principles

- Environment variables configure services.
- Database migrations run during controlled releases.
- Queue workers are supervised.
- Storage is backed up.
- Uploaded files must use Laravel Storage disks rather than hardcoded provider paths.
- CI must pass Composer validation, tests, Pint, PHPStan/Larastan, Rector dry-run, frontend build, browser tests, and cache checks before deployment.

## Release Verification

Before promoting a release, run:

- `composer install --no-interaction --prefer-dist --optimize-autoloader`
- `composer quality`
- `npm ci`
- `npm run build`
- `npm run test:e2e`
- `php artisan config:cache`
- `php artisan route:cache`
- `php artisan view:cache`

Queue workers should be restarted after deployment so new event listeners and queued job classes are loaded.
When mounting persistent storage volumes, the production entrypoint repairs `storage` and `bootstrap/cache` ownership before warming Laravel caches. Do not remove this step; build-time file ownership does not apply to runtime-mounted volumes.

Do not bind-mount the application checkout into the production Nginx container. The Nginx image stage must serve the same `public/build` manifest and assets that the Laravel image was built with; otherwise Blade-rendered Vite paths can drift from the files served at the edge.

Rollback plan:

- Restore the previous container image or release artifact.
- Restore the last verified database backup if migrations are not backward-compatible.
- Run `php artisan optimize:clear`, then rebuild config, route, and view caches.
- Restart Supervisor-managed queue workers and scheduler loop.
- Verify `/healthz`, `/version`, and the latest `civic_intelligence_runs` status.

## Health Checks

Use `GET /healthz` for load balancer and uptime checks. The endpoint reports app, database, cache, storage, and queue readiness without exposing secrets or internal paths.

Use `GET /version` during release verification to confirm deployed application version, environment label, and commit metadata. Authenticated operators can use `GET /admin/system/metrics` to review queue, scheduler, database, cache, storage, and integrity-run metrics.

For Release Candidate 1, `APP_VERSION` must be `v1.0.0-RC1` and `APP_COMMIT` must match the deployed Git SHA or release artifact identifier.

RC1 Docker validation used manual containers. Docker Compose is available for operator deployments, but `docker-compose.production.yml` requires a real `.env.production` file. A real production deployment should use the Compose stack or host-native equivalents with HTTPS, durable storage, backups, and external monitoring.

## Scheduler

The scheduler should run continuously in production. Sprint 13 part 1 schedules `civiclens:integrity-run` daily at 02:15 to generate deterministic civic integrity indicators from active rules.

## V2 Expansion Notes

Add separate workers for OCR, indexing, analytics, and public API workloads.
