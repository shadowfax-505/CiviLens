<?php

use App\Models\Role;
use App\Models\User;
use App\Services\Ingestion\RobotsPolicy;
use Illuminate\Support\Facades\Cache;

if (! function_exists('searchAdminUser')) {
    function searchAdminUser(): User
    {
        $role = Role::query()->create(['name' => 'Administrator', 'slug' => config('civiclens.roles.admin')]);
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->roles()->attach($role);

        return $user;
    }
}

/**
 * Record that a host publishes no robots.txt restrictions.
 *
 * Acquisition fetches robots.txt before anything else and refuses when it
 * cannot be read, so a faked publisher that says nothing about robots is a
 * publisher we may not crawl. Seeding the parsed record states the same thing a
 * 404 would, without adding a request that would reorder what a test observes.
 */
function robotsAbsentFor(string ...$hosts): void
{
    foreach ($hosts as $host) {
        Cache::put('robots:'.$host, [
            'status' => 'absent',
            'fetched_at' => now()->toIso8601String(),
        ], 3600);
    }
}

/**
 * Record a host's robots.txt as saying exactly this.
 */
function robotsFor(string $host, string $body): void
{
    $parsed = app(RobotsPolicy::class)->parse($body);

    Cache::put('robots:'.$host, $parsed + [
        'status' => 'present',
        'fetched_at' => now()->toIso8601String(),
    ], 3600);
}
