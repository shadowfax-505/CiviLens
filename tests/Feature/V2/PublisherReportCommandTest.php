<?php

use App\Models\SourcePublisher;
use App\Models\TenderObservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

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

it('saves a dated snapshot so an unattended crawl leaves a trail', function (): void {
    // A report that only exists while someone is watching is not a trail. The
    // crawl runs for days without anyone present, and the counts it produces
    // should still be readable afterwards.
    Storage::fake('local');
    $publisher = reportPublisher();
    reportObservation($publisher, '1302100', 'Amendment/Corrigendum issued : 1', '2026-08-11 02:00:06');

    $exit = Artisan::call('civiclens:publisher-report', ['slug' => 'bppa-egp', '--save' => true]);

    expect($exit)->toBe(0);

    $path = 'reports/bppa-egp/'.now()->toDateString().'.json';
    Storage::disk('local')->assertExists($path);

    $saved = json_decode((string) Storage::disk('local')->get($path), true);

    expect($saved['publisher'])->toBe('bppa-egp')
        ->and($saved['amendments']['amended_notices'])->toBe(1)
        // The snapshot records when it was taken, or a trail of files says
        // nothing about when each count was true.
        ->and($saved['observed_at'])->not->toBeEmpty();
});
