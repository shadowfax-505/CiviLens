# 11 Deployment Guide

## V1 Local Environment

Use Docker or Laravel Sail for PHP, MySQL, Redis, Meilisearch, and mail testing.

## Production Container Stack

Sprint 13 part 1 adds a production-oriented Docker scaffold:

- `Dockerfile` builds Composer dependencies, Vite assets, a PHP 8.4 PHP-FPM runtime with the Redis extension, and an Nginx runtime stage with the same immutable public assets.
- `docker-compose.production.yml` defines independently deployable web, worker, scheduler, image-backed Nginx, and an opt-in migration release role. MySQL and Redis are opt-in bundled validation services; normal production deployments inject managed service hosts and credentials.
- `docker/production/nginx.conf` serves public assets from the release image and forwards PHP requests to PHP-FPM.
- The web role owns PHP-FPM, the worker role runs `queue:work`, and the scheduler role runs `schedule:work`. Workers and the scheduler receive a two-minute graceful-stop window so Laravel can finish an in-flight job or scheduler tick.
- `docker/production/php.ini` sets production PHP limits and disables error display.
- `.env.production.example` documents runtime variables, including durable object storage. `.env.release.example` documents the separate migration-only database principal.

Use this scaffold as a deployable baseline. Production secrets must be injected through the hosting environment, not committed.

For Docker Compose deployments, copy `.env.production.example` to `.env.production`, set a real `APP_KEY`, replace all database credentials, and run Compose with the production environment file:

```bash
docker compose --env-file .env.production -f docker-compose.production.yml up -d --build
```

Use managed MySQL and Redis by default. To run the bundled services only for local production-style validation, set `MYSQL_*` and `REDIS_PASSWORD` and add `--profile bundled`.

Run migrations as a controlled, one-shot release step with a migration-only database principal. Copy `.env.release.example` to `.env.release`, grant that principal only the schema privileges required for migrations, then run:

```bash
docker compose --env-file .env.production -f docker-compose.production.yml --profile release run --rm release
```

The release role runs only `php artisan migrate --force --no-interaction` as `www-data`; it does not receive runtime mail, cache, queue, object-storage, or application database credentials. Do not run migrations automatically from the web container entrypoint.

## Container Roles And Health Checks

- `app` runs PHP-FPM and warms Laravel caches only for the web role. It is healthy when PHP-FPM accepts connections.
- `worker` runs one Laravel `queue:work` process as PID 1. Docker sends it `SIGTERM`; Laravel receives that signal directly and the Compose grace period allows an active job to finish.
- `scheduler` runs one Laravel `schedule:work` process as PID 1 with the same graceful-stop window.
- `nginx` waits for the app health check and probes its own `/nginx-healthz` ingress path. This prevents a queue or integrity metric from draining healthy HTTP traffic.
- MySQL and Redis publish native readiness checks only in the opt-in bundled profile. Monitor worker/scheduler process health and authenticated `/admin/system/metrics` separately from ingress readiness.

CI validates both the normal and `release` Compose profiles using the committed example environment only; it never builds or starts the production stack.

## Production Map Configuration

Set the following values in `.env.production` for public and administrative map behavior. The supplied values are Bangladesh-wide defaults and OpenStreetMap attribution; adjust the center, viewport caps, tile provider, and attribution for the deployed jurisdiction and provider terms.

- `MAP_PUBLIC_MARKER_LIMIT` and `MAP_ADMIN_MARKER_LIMIT` bound map response sizes.
- `MAP_DEFAULT_LATITUDE`, `MAP_DEFAULT_LONGITUDE`, `MAP_MAX_VIEWPORT_LATITUDE_SPAN`, and `MAP_MAX_VIEWPORT_LONGITUDE_SPAN` control the initial map and viewport limits.
- `MAP_TILE_URL` and `MAP_TILE_ATTRIBUTION` configure the basemap without changing application code.

## Durable Object Storage

For production uploads, set `FILESYSTEM_DISK`, `DOCUMENT_STORAGE_DISK`, and `FILESYSTEM_CLOUD` to `s3` together with `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, and the optional `AWS_URL`, `AWS_ENDPOINT`, and `AWS_USE_PATH_STYLE_ENDPOINT` values for S3-compatible storage. Keep credentials in the deployment secret manager, never in the repository.

## Deployment Principles

- Environment variables configure services.
- Database migrations run during controlled releases.
- Queue workers and the scheduler run as separate supervised/container process roles.
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

### Civic Earth rollout and rollback

The Earth-to-Bangladesh journey is guarded by `CIVICLENS_EARTH_JOURNEY_ENABLED`, which defaults to `false`. Enable it only after the production image has been built with the pinned local Cesium assets and `public/images/orbital/manifest.json` matches the approved local textures:

```bash
CIVICLENS_EARTH_JOURNEY_ENABLED=true
php artisan optimize:clear
php artisan config:cache
```

Environment changes are not visible while Laravel configuration is cached until the cache is rebuilt. To roll back only the journey, set the flag to `false`, run the same cache commands, and verify `/`; the established static hero returns without a route, controller, form, permission, or database change. Restoring the previous container image remains the full-release rollback.

The production artifact must contain Vite's `public/build/cesium` directory and both approved local orbital textures. Do not replace them with daily browser tiles. The weekly candidate workflow writes only to `public/images/orbital/candidates/` on a review branch; merging that review does not promote a candidate into the runtime paths. Promotion requires a separate explicit visual approval and release change.

Rollback plan:

- Restore the previous container image or release artifact.
- Restore the last verified database backup if migrations are not backward-compatible.
- Run `php artisan optimize:clear`, then rebuild config, route, and view caches.
- Restart the worker and scheduler roles.
- Verify `/healthz`, `/version`, and the latest `civic_intelligence_runs` status.

## Health Checks

Use `/nginx-healthz` for container ingress readiness and `GET /healthz` for public-safe application dependency diagnostics. A degraded `/healthz` does not prove that Nginx or PHP-FPM is unavailable; authenticated operations monitoring must also inspect queue, scheduler, and integrity status.

Use `GET /version` during release verification to confirm deployed application version, environment label, and commit metadata. Authenticated operators can use `GET /admin/system/metrics` to review queue, scheduler, database, cache, storage, and integrity-run metrics.

For stable v1.0.0, `APP_VERSION` must be `v1.0.0` and `APP_COMMIT` must match the deployed Git SHA or release artifact identifier.

Historical RC1 Docker validation used production image targets and an isolated Docker Compose smoke stack. Runtime services require a real `.env.production`; the one-shot release role also requires `.env.release` with its migration-only principal. A real production deployment should use the Compose stack or host-native equivalents with HTTPS, durable storage, backups, and external monitoring.

## Scheduler

The scheduler should run continuously in production. Sprint 13 part 1 schedules `civiclens:integrity-run` daily at 02:15 to generate deterministic civic integrity indicators from active rules. V2 Stage 3 schedules `civiclens:sources-dispatch` every five minutes; it dispatches only due, active, unpaused allowlisted endpoints and does no remote work inside the scheduler process.

## Governed Acquisition Runtime

Keep `INGESTION_ENABLED=false` until the first source cohort, durable private `INGESTION_ARTIFACT_DISK`, Redis-backed rate limits, supervised `ingestion` queue workers, egress controls, and malware scanner are ready. The browser-render provider is intentionally unavailable by default; configure it only as an isolated worker with no application secrets and no unrestricted network access.

Every enabled acquisition worker must have:

- HTTPS egress restricted to approved public publisher hosts, with DNS pinning left enabled. Pinning is fail-closed: when `CURLOPT_RESOLVE` is unavailable or `INGESTION_PIN_RESOLVED_ADDRESS=false`, ingestion refuses to send the request. `INGESTION_ALLOW_UNPINNED_EGRESS=true` overrides that refusal and must stay `false` outside egress-proxy environments where `CURLOPT_RESOLVE` is actively wrong; `ext-curl` is a hard runtime requirement;
- access to the private artifact disk but no public web mount for `ingestion/`;
- ClamAV-compatible `clamdscan` access or a replacement bound to `MalwareScanner`;
- queue retry and failure monitoring;
- limits equal to or stricter than the committed discovery and artifact byte caps;
- `poppler-utils` for native PDF extraction. `pdftotext` and `pdfinfo` are hard runtime requirements once extraction is enabled: the application image installs them, and `EXTRACTION_PDFTOTEXT_BINARY`/`EXTRACTION_PDFINFO_BINARY` should be absolute paths in production. CI installs the same package and verifies it before running the suite, so a missing toolchain fails loudly rather than silently skipping coverage.

Scanner failure is fail-closed: the snapshot is retained in the private quarantine path and cannot become an accepted document input. A deployment may use an external ClamAV daemon or a dedicated scanning worker; the application contract remains provider-neutral. Set `INGESTION_ENABLED=false` to stop new scheduled discovery immediately, and use the endpoint pause controls for cohort-level rollback. Existing artifacts and audit history are never deleted by rollback.

## V2 Expansion Notes

Add separate workers for OCR, indexing, analytics, and public API workloads. Keep ingestion and parser/OCR workers independently scalable.
