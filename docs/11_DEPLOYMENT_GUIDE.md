# 11 Deployment Guide

## V1 Local Environment

Use Docker or Laravel Sail for PHP, MySQL, Redis, Meilisearch, and mail testing.

## Production Container Stack

Sprint 13 part 1 adds a production-oriented Docker scaffold:

- `Dockerfile` builds Composer dependencies, Vite assets, a PHP 8.4 PHP-FPM runtime with the Redis extension, and an Nginx runtime stage with the same immutable public assets.
- `docker-compose.production.yml` defines independently deployable web, worker, scheduler, image-backed Nginx, MySQL, and Redis services. The release migration role is opt-in and never starts with the long-running stack.
- `docker/production/nginx.conf` serves public assets from the release image and forwards PHP requests to PHP-FPM.
- The web role owns PHP-FPM, the worker role runs `queue:work`, and the scheduler role runs `schedule:work`. Workers and the scheduler receive a two-minute graceful-stop window so Laravel can finish an in-flight job or scheduler tick.
- `docker/production/php.ini` sets production PHP limits and disables error display.
- `.env.production.example` documents required production environment variables.

Use this scaffold as a deployable baseline. Production secrets must be injected through the hosting environment, not committed.

For Docker Compose deployments, copy `.env.production.example` to `.env.production`, set a real `APP_KEY`, replace all database credentials, and run Compose with the production environment file:

```bash
docker compose --env-file .env.production -f docker-compose.production.yml up -d --build
```

Keep `DB_*` and `MYSQL_*` credentials synchronized. Laravel reads `DB_*`; the MySQL container reads `MYSQL_*`.

Run migrations as a controlled, one-shot release step after the database and Redis services are healthy, before starting a new application release:

```bash
docker compose --env-file .env.production -f docker-compose.production.yml --profile release run --rm release
```

The release role runs only `php artisan migrate --force --no-interaction`; it does not start queue workers or the scheduler and is configured not to restart. Do not run migrations automatically from the web container entrypoint.

## Container Roles And Health Checks

- `app` runs PHP-FPM and warms Laravel caches only for the web role. It is healthy when PHP-FPM accepts connections.
- `worker` runs one Laravel `queue:work` process as PID 1. Docker sends it `SIGTERM`; Laravel receives that signal directly and the Compose grace period allows an active job to finish.
- `scheduler` runs one Laravel `schedule:work` process as PID 1 with the same graceful-stop window.
- `nginx` waits for the app health check and probes the public-safe `/healthz` endpoint.
- MySQL and Redis publish native readiness checks. App, worker, scheduler, Nginx, and the release role wait on the dependencies they require.

CI validates both the normal and `release` Compose profiles using the committed example environment only; it never builds or starts the production stack.

## Production Map Configuration

Set the following values in `.env.production` for public and administrative map behavior. The supplied values are Bangladesh-wide defaults and OpenStreetMap attribution; adjust the center, viewport caps, tile provider, and attribution for the deployed jurisdiction and provider terms.

- `MAP_PUBLIC_MARKER_LIMIT` and `MAP_ADMIN_MARKER_LIMIT` bound map response sizes.
- `MAP_DEFAULT_LATITUDE`, `MAP_DEFAULT_LONGITUDE`, `MAP_MAX_VIEWPORT_LATITUDE_SPAN`, and `MAP_MAX_VIEWPORT_LONGITUDE_SPAN` control the initial map and viewport limits.
- `MAP_TILE_URL` and `MAP_TILE_ATTRIBUTION` configure the basemap without changing application code.

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

Frontend customization is deploy-safe when it stays inside Blade views, shared components, `resources/css/app.css`, and `resources/js/app.js` while preserving route names, form fields, policies, and the Vite manifest pipeline. See `docs/31_FRONTEND_CUSTOMIZATION_GUIDE.md`.

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

RC1 Docker validation used production image targets and an isolated Docker Compose smoke stack. `docker-compose.production.yml` requires a real `.env.production` file and should be run with `--env-file .env.production` when deploying. A real production deployment should use the Compose stack or host-native equivalents with HTTPS, durable storage, backups, and external monitoring.

## Scheduler

The scheduler should run continuously in production. Sprint 13 part 1 schedules `civiclens:integrity-run` daily at 02:15 to generate deterministic civic integrity indicators from active rules.

## V2 Expansion Notes

Add separate workers for OCR, indexing, analytics, and public API workloads.
