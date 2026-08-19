<?php

use App\Models\PublicationSnapshot;
use App\Services\Publication\PublicationSnapshotBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('publishes accepted values and no others', function (): void {
    // A deferral is the system declining to stand behind a value. Publishing
    // one under a caveat would still be publishing it.
    ['page' => $page] = publishableDocument();

    certifiedFieldOn($page, 'accepted');
    certifiedFieldOn($page, 'deferred');
    certifiedFieldOn($page, 'pending');

    $snapshot = app(PublicationSnapshotBuilder::class)->build('test');

    expect($snapshot->figure_count)->toBe(1)
        ->and($snapshot->figures()->first()->extracted_value)->toBe('১,২৩,৪৫৬');
});

it('carries the level a figure was certified at, and the basis of it', function (): void {
    // ADR-018: a group certified at 0.20 is not comparable with one at 0.05,
    // and a borrowed threshold is not a conditional claim about the group it
    // was borrowed for. Both travel with the figure.
    ['page' => $page] = publishableDocument();

    certifiedFieldOn($page, 'accepted', ['decision_alpha' => 0.10, 'decision_basis' => 'script']);

    $figure = app(PublicationSnapshotBuilder::class)->build('test')->figures()->first();

    expect($figure->certified_alpha)->toBe(0.10)
        ->and($figure->decision_basis)->toBe('script')
        ->and($figure->isConditional())->toBeFalse();
});

it('keeps a document that certified nothing, with a count of zero', function (): void {
    // ADR-020 settled this for an endpoint that produced no documents, and it
    // applies to a document that produced no figures. Dropping the zeroes
    // would make the corpus look like it certifies everything it holds.
    publishableDocument();

    $snapshot = app(PublicationSnapshotBuilder::class)->build('test');

    expect($snapshot->document_count)->toBe(1)
        ->and($snapshot->figure_count)->toBe(0)
        ->and($snapshot->documents()->first()->figure_count)->toBe(0);
});

it('separates archived web pages from published documents', function (): void {
    // 476 artifacts is not 476 documents. Conflating them overstates the
    // corpus by a factor of forty.
    publishableDocument('application/pdf');
    publishableDocument('text/html');

    $snapshot = app(PublicationSnapshotBuilder::class)->build('test');

    expect($snapshot->documents()->where('is_document', true)->count())->toBe(1)
        ->and($snapshot->documents()->where('is_document', false)->count())->toBe(1);
});

it('counts a certified value it cannot trace to a document rather than dropping it quietly', function (): void {
    // The evidence is the point. A figure with no source is an assertion, so
    // it is not published — but a snapshot that publishes less than it should
    // has to say so on its face.
    // Quarantine keeps the document out of the snapshot without removing the
    // fields already read from it. Artifacts are immutable once written, so
    // this is set where it would really be set — at acquisition.
    ['page' => $page] = publishableDocument('application/pdf', quarantined: true);

    certifiedFieldOn($page, 'accepted');

    $snapshot = app(PublicationSnapshotBuilder::class)->build('test');

    expect($snapshot->figure_count)->toBe(0)
        ->and($snapshot->skipped_figure_count)->toBe(1);
});

it('supersedes the published snapshot without making the old one unresolvable', function (): void {
    // A figure someone has cited has to stay reachable after the next build.
    // That is why a snapshot copies its values instead of pointing at rows the
    // pipeline will rewrite.
    ['page' => $page] = publishableDocument();
    certifiedFieldOn($page, 'accepted');

    $builder = app(PublicationSnapshotBuilder::class);

    $first = $builder->publish($builder->build('first'));
    $second = $builder->publish($builder->build('second'));

    expect(PublicationSnapshot::current()->id)->toBe($second->id)
        ->and($first->fresh()->isPublished())->toBeTrue()
        ->and($first->figures()->count())->toBe(1);
});

it('does not serve a snapshot that was built but never published', function (): void {
    // Building is not publishing. A snapshot can be inspected before anyone
    // sees it.
    ['page' => $page] = publishableDocument();
    certifiedFieldOn($page, 'accepted');

    app(PublicationSnapshotBuilder::class)->build('unreviewed');

    expect(PublicationSnapshot::current())->toBeNull();
});

it('distinguishes two snapshots that rest on different calibrations', function (): void {
    // Identical counts can sit on different thresholds. A reader comparing two
    // snapshots must be able to see that the numbers do not mean the same thing.
    ['page' => $page] = publishableDocument();
    $field = certifiedFieldOn($page, 'accepted');

    $builder = app(PublicationSnapshotBuilder::class);
    $before = $builder->build('before');

    $field->forceFill(['decision_alpha' => 0.20, 'decision_basis' => 'marginal'])->save();

    $after = $builder->build('after');

    expect($before->figure_count)->toBe($after->figure_count)
        ->and($before->calibration_digest)->not->toBe($after->calibration_digest);
});
