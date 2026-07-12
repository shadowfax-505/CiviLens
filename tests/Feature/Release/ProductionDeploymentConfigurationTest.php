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
        ->toContain('/healthz');
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
