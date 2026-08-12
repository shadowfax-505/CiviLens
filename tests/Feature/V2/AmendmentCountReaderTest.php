<?php

use App\Models\SourcePublisher;
use App\Models\TenderObservation;
use App\Services\Intelligence\AmendmentCountReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function amendmentPublisher(): SourcePublisher
{
    return SourcePublisher::query()->firstOrCreate(['slug' => 'egp'], [
        'name' => 'Bangladesh Public Procurement Authority',
        'source_class' => 'government',
        'canonical_url' => 'https://www.eprocure.gov.bd/',
        'attribution_name' => 'Bangladesh Public Procurement Authority',
        'is_active' => true,
        'metadata' => [],
    ]);
}

function observation(string $status, string $externalId = '1'): TenderObservation
{
    return TenderObservation::query()->create([
        'source_publisher_id' => amendmentPublisher()->getKey(),
        'external_id' => $externalId,
        'status' => $status,
        'observed_at' => now(),
        'observation_hash' => hash('sha256', $externalId.$status.microtime()),
    ]);
}

it('reads the amendment count the publisher printed', function (): void {
    // Wording taken from the live listing. Reading a number the publisher
    // stated is not inference.
    $reader = app(AmendmentCountReader::class);

    expect($reader->declaredOn(observation('Amendment/Corrigendum issued : 2')))->toBe(2)
        ->and($reader->declaredOn(observation('Amendment/Corrigendum issued : 1', '2')))->toBe(1)
        ->and($reader->declaredOn(observation('Live', '3')))->toBeNull()
        ->and($reader->declaredOn(observation('Cancelled', '4')))->toBeNull();
});

it('treats a marker without a number as one amendment', function (): void {
    expect(app(AmendmentCountReader::class)->declaredOn(observation('Corrigendum issued')))->toBe(1);
});

it('distinguishes none declared from none occurred', function (): void {
    // Returning zero would assert something the listing did not say.
    expect(app(AmendmentCountReader::class)->declaredOn(observation('Live')))->toBeNull();
});

it('counts each notice once, using its most recent observation', function (): void {
    // A notice observed repeatedly must not count repeatedly, or a long crawl
    // would inflate every publisher's totals.
    observation('Live', '100');
    observation('Amendment/Corrigendum issued : 1', '100');
    observation('Live', '200');

    $summary = app(AmendmentCountReader::class)->summarise(amendmentPublisher()->getKey());

    expect($summary['notices'])->toBe(2)
        ->and($summary['amended_notices'])->toBe(1)
        ->and($summary['amendments'])->toBe(1)
        ->and($summary['amended_share'])->toBe(0.5);
});

it('never characterises an amendment as wrongdoing', function (): void {
    observation('Amendment/Corrigendum issued : 3', '300');

    $statement = app(AmendmentCountReader::class)->summarise(amendmentPublisher()->getKey())['statement'];

    foreach (['corrupt', 'fraud', 'suspicious', 'irregular', 'manipulat', 'guilty', 'improper'] as $word) {
        expect(mb_strtolower($statement))->not->toContain($word);
    }

    expect($statement)->toContain('not that anything was wrong');
});

it('says so plainly when a publisher has no observations', function (): void {
    expect(app(AmendmentCountReader::class)->summarise(amendmentPublisher()->getKey() + 999)['statement'])
        ->toContain('No notices have been observed');
});
