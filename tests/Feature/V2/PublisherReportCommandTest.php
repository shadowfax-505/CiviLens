<?php

use App\Models\SourcePublisher;
use App\Models\TenderObservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function reportPublisher(): SourcePublisher
{
    return SourcePublisher::query()->create([
        'slug' => 'bppa-egp',
        'name' => 'Bangladesh Public Procurement Authority',
        'source_class' => 'government',
        'canonical_url' => 'https://www.eprocure.gov.bd/',
        'attribution_name' => 'Bangladesh Public Procurement Authority',
        'is_active' => true,
        'metadata' => [],
    ]);
}

function reportObservation(SourcePublisher $publisher, string $externalId, string $status, string $observedAt): void
{
    TenderObservation::query()->create([
        'source_publisher_id' => $publisher->getKey(),
        'external_id' => $externalId,
        'status' => $status,
        'observed_at' => $observedAt,
        'observation_hash' => hash('sha256', $externalId.$status.$observedAt),
    ]);
}

it('reports what a publisher declared and what it later changed', function (): void {
    // Both indicators count what the publisher itself stated, so both work
    // before any calibration exists. Taken from the live crawl: notice 1302100
    // moved from Live to Being processed between two crawls ten hours apart.
    $publisher = reportPublisher();
    reportObservation($publisher, '1302100', 'Live', '2026-08-11 02:00:06');
    reportObservation($publisher, '1302100', 'Being processed', '2026-08-11 12:00:24');
    reportObservation($publisher, '1309032', 'Amendment/Corrigendum issued : 2', '2026-08-11 02:00:06');

    $exit = Artisan::call('civiclens:publisher-report', ['slug' => 'bppa-egp']);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('"revised_notices": 1')
        ->and($output)->toContain('1 of 2 observed notices declared an amendment')
        ->and($output)->toContain('not that anything was wrong')
        // The report states changes; it never characterises them.
        ->and(mb_strtolower($output))->not->toContain('suspicious')
        ->and(mb_strtolower($output))->not->toContain('irregular');
});

it('refuses a slug that is not registered rather than reporting nothing', function (): void {
    // Reporting an empty result for an unknown publisher would read as "this
    // publisher has changed nothing", which is a different claim entirely.
    $exit = Artisan::call('civiclens:publisher-report', ['slug' => 'not-a-publisher']);

    expect($exit)->toBe(1)
        ->and(Artisan::output())->toContain('No publisher is registered');
});
