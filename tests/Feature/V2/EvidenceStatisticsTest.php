<?php

use App\Services\Publication\PublicationSnapshotBuilder;
use App\Services\PublicPortal\EvidenceStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows what was withheld beside what was published', function (): void {
    // "14,277 certified figures" invites a reader to treat that as the contents
    // of these documents. It is the part the system was willing to stand behind,
    // and the share it was not belongs in the same sentence.
    ['page' => $page] = publishableDocument();

    certifiedFieldOn($page, 'accepted');
    certifiedFieldOn($page, 'deferred');
    certifiedFieldOn($page, 'deferred');
    certifiedFieldOn($page, 'deferred');

    $builder = app(PublicationSnapshotBuilder::class);
    $builder->publish($builder->build('published'));

    $stats = app(EvidenceStatisticsService::class)->build();

    expect($stats['published'])->toBe(1)
        ->and($stats['withheld'])->toBe(3)
        ->and($stats['share_withheld'])->toBe(0.75);
});

it('refuses to total the figures, and says why', function (): void {
    // They sit beside one another in crore, in taka and as percentages, on
    // pages that declare the unit in a heading this system does not read. A sum
    // would have no referent and would be the number everyone quoted.
    ['page' => $page] = publishableDocument();
    certifiedFieldOn($page, 'accepted');

    $builder = app(PublicationSnapshotBuilder::class);
    $builder->publish($builder->build('published'));

    $stats = app(EvidenceStatisticsService::class)->build();

    expect($stats)->not->toHaveKey('total')
        ->and($stats['why_there_are_no_totals'])->toContain('counted, never added');
});

it('reports the error rate with the interval around it', function (): void {
    // M5 established that at this sample the interval reaches past the level
    // being claimed. Quoting the rate alone would claim a precision these
    // judgements cannot support.
    ['page' => $page] = publishableDocument();

    certifiedFieldOn($page, 'accepted', ['gold_source' => 'reviewer', 'is_correct' => true]);
    certifiedFieldOn($page, 'accepted', ['gold_source' => 'reviewer', 'is_correct' => false]);

    $builder = app(PublicationSnapshotBuilder::class);
    $builder->publish($builder->build('published'));

    $rate = app(EvidenceStatisticsService::class)->build()['error_rate_among_judged'];

    expect($rate['rate'])->toBe(0.5)
        ->and($rate['interval']['low'])->toBeLessThan(0.5)
        ->and($rate['interval']['high'])->toBeGreaterThan(0.5);
});

it('shows a publisher whose every value was withheld as exactly that', function (): void {
    // cbad has 69 candidates and no calibration, so all of them defer. A zero
    // published beside a zero withheld would look the same as a publisher
    // nobody has read.
    ['page' => $page] = publishableDocument();

    certifiedFieldOn($page, 'deferred', ['publisher_group' => 'cbad-bangladesh']);
    certifiedFieldOn($page, 'deferred', ['publisher_group' => 'cbad-bangladesh']);

    $builder = app(PublicationSnapshotBuilder::class);
    $builder->publish($builder->build('published'));

    $stats = app(EvidenceStatisticsService::class)->build();

    expect($stats['withheld'])->toBe(2)
        ->and($stats['published'])->toBe(0)
        ->and($stats['share_withheld'])->toBe(1.0);
});

it('says nothing at all before a snapshot is published', function (): void {
    expect(app(EvidenceStatisticsService::class)->build()['snapshot'])->toBeNull();
});
