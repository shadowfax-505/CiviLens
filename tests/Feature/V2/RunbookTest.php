<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/**
 * A runbook that drifts is worse than none: an operator trusts it and it is
 * wrong. Nineteen commands existed and appeared in no document at all before
 * this was written, including every one built in the week that added them.
 */
it('writes down every command an operator can run', function (): void {
    $runbook = (string) file_get_contents(base_path('docs/RUNBOOK.md'));

    $undocumented = collect(array_keys(Artisan::all()))
        ->filter(fn (string $name): bool => str_starts_with($name, 'civiclens:'))
        ->reject(fn (string $name): bool => str_contains($runbook, $name))
        ->values()
        ->all();

    expect($undocumented)->toBe([]);
});

it('does not describe an API the application does not serve', function (): void {
    // 314 lines specifying an interface that does not exist. Kept as a design
    // record, but a reader who takes it for a description of the system has
    // been misled by the documentation rather than by the code.
    $apiRoutes = collect(Route::getRoutes())
        ->filter(fn ($route): bool => str_starts_with((string) $route->uri(), 'api/'))
        ->count();

    $specification = (string) file_get_contents(base_path('docs/09_API_SPECIFICATION.md'));

    if ($apiRoutes === 0) {
        expect($specification)->toContain('Not built.');
    } else {
        // Once an API exists, the warning is the thing that has gone stale.
        expect($specification)->not->toContain('Not built.');
    }
});
