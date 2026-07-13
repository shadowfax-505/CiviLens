<?php

it('defines independently runnable production web, worker, scheduler, and release roles', function (): void {
    $compose = file_get_contents(base_path('docker-compose.production.yml'));

    expect($compose)
        ->toContain("  app:\n")
        ->toContain("  worker:\n")
        ->toContain("  scheduler:\n")
        ->toContain("  release:\n")
        ->toContain('queue:work')
        ->toContain('schedule:work')
        ->toContain('"migrate", "--force", "--no-interaction"')
        ->toContain('condition: service_healthy');
});

it('configures production container health checks and graceful worker shutdown', function (): void {
    $compose = file_get_contents(base_path('docker-compose.production.yml'));

    expect($compose)
        ->toContain('healthcheck:')
        ->toContain('start_period:')
        ->toContain('stop_grace_period:')
        ->toContain('/nginx-healthz');
});

it('keeps the production entrypoint from warming caches for background roles', function (): void {
    $entrypoint = file_get_contents(base_path('docker/production/entrypoint.sh'));

    expect($entrypoint)
        ->toContain('CIVICLENS_RUNTIME_ROLE')
        ->toContain('web')
        ->toContain('php artisan optimize');
});

it('provides production map settings without embedding environment-specific secrets', function (): void {
    $environment = file_get_contents(base_path('.env.production.example'));

    expect($environment)
        ->toContain('MAP_PUBLIC_MARKER_LIMIT=')
        ->toContain('MAP_ADMIN_MARKER_LIMIT=')
        ->toContain('MAP_DEFAULT_LATITUDE=')
        ->toContain('MAP_DEFAULT_LONGITUDE=')
        ->toContain('MAP_TILE_URL=')
        ->toContain('MAP_TILE_ATTRIBUTION=');
});

it('keeps release credentials isolated and makes bundled dependencies opt-in', function (): void {
    $compose = file_get_contents(base_path('docker-compose.production.yml'));
    $entrypoint = file_get_contents(base_path('docker/production/entrypoint.sh'));

    expect($compose)
        ->toContain('path: .env.release')
        ->toContain('required: false')
        ->toContain('profiles: ["bundled"]')
        ->toContain('test -n \"$$REDIS_PASSWORD\"')
        ->not->toContain('MYSQL_PASSWORD:-change-me')
        ->not->toContain('MYSQL_ROOT_PASSWORD:-change-root');

    expect($entrypoint)
        ->toContain('RELEASE_DB_HOST')
        ->toContain('RELEASE_DB_PASSWORD');
});

it('separates nginx ingress readiness from aggregate application metrics', function (): void {
    $compose = file_get_contents(base_path('docker-compose.production.yml'));
    $nginx = file_get_contents(base_path('docker/production/nginx.conf'));

    expect($compose)
        ->toContain('/nginx-healthz')
        ->not->toContain('wget --spider -q http://127.0.0.1/healthz');

    expect($nginx)->toContain('location = /nginx-healthz');
});

it('documents complete object storage and migration-only release contracts', function (): void {
    $productionEnvironment = file_get_contents(base_path('.env.production.example'));
    $releaseEnvironment = file_get_contents(base_path('.env.release.example'));

    expect($productionEnvironment)
        ->toContain('AWS_ACCESS_KEY_ID=')
        ->toContain('AWS_SECRET_ACCESS_KEY=')
        ->toContain('AWS_DEFAULT_REGION=')
        ->toContain('AWS_BUCKET=')
        ->toContain('AWS_ENDPOINT=')
        ->toContain('AWS_USE_PATH_STYLE_ENDPOINT=');

    expect($releaseEnvironment)
        ->toContain('RELEASE_APP_KEY=')
        ->toContain('RELEASE_DB_HOST=')
        ->toContain('RELEASE_DB_PASSWORD=');
});
