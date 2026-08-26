<?php

use App\Models\DiscoveredResource;
use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\ExtractionRun;
use App\Models\Role;
use App\Models\SourceArtifactVersion;
use App\Models\SourceEndpoint;
use App\Models\User;
use App\Services\Ingestion\RobotsPolicy;
use Illuminate\Support\Facades\Cache;

if (! function_exists('searchAdminUser')) {
    function searchAdminUser(): User
    {
        // firstOrCreate, so a test needing two reviewers can call this twice.
        $role = Role::query()->firstOrCreate(
            ['slug' => config('civiclens.roles.admin')],
            ['name' => 'Administrator'],
        );
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

if (! function_exists('publishableDocument')) {
    /**
     * A document with one page, ready for fields to be hung off it.
     *
     * @return array{artifact: SourceArtifactVersion, page: ExtractionPage}
     */
    function publishableDocument(string $mediaType = 'application/pdf', bool $quarantined = false): array
    {
        $endpoint = SourceEndpoint::factory()->create();
        $resource = DiscoveredResource::factory()->create(['source_endpoint_id' => $endpoint->id]);

        $artifact = SourceArtifactVersion::factory()->create([
            'discovered_resource_id' => $resource->id,
            'media_type' => $mediaType,
            'is_quarantined' => $quarantined,
        ]);

        $run = ExtractionRun::factory()->create([
            'source_artifact_version_id' => $artifact->id,
            'page_count' => 1,
        ]);

        $page = ExtractionPage::factory()->create([
            'extraction_run_id' => $run->id,
            'page_number' => 1,
        ]);

        return ['artifact' => $artifact, 'page' => $page];
    }

    function certifiedFieldOn(ExtractionPage $page, string $decision, array $overrides = []): ExtractionField
    {
        return ExtractionField::factory()->create(array_merge([
            'extraction_run_id' => $page->extraction_run_id,
            'extraction_page_id' => $page->id,
            'field_type' => 'amount',
            'extracted_value' => '১,২৩,৪৫৬',
            'decision' => $decision,
            'decision_alpha' => 0.05,
            'decision_basis' => $decision === 'accepted' ? 'group' : null,
            'publisher_group' => 'dgcivil-bangladesh',
            'script_class' => 'bn',
        ], $overrides));
    }
}
