<?php

use App\Services\Publication\PublicationSnapshotBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not serve a page for a figure the site is not publishing', function (): void {
    // A snapshot is what the site stands behind, so what can be traced is
    // exactly what was published. A value withheld, deferred or rejected has no
    // page here, because there is no claim about it to check.
    ['page' => $page] = publishableDocument();
    certifiedFieldOn($page, 'accepted');

    $builder = app(PublicationSnapshotBuilder::class);
    $superseded = $builder->publish($builder->build('first'));
    $builder->publish($builder->build('second'));

    $this->get(route('public.evidence.figure.page', $superseded->figures()->firstOrFail()))
        ->assertNotFound();
});

it('offers the page a published figure was read from', function (): void {
    ['page' => $page] = publishableDocument();
    certifiedFieldOn($page, 'accepted');

    $builder = app(PublicationSnapshotBuilder::class);
    $snapshot = $builder->publish($builder->build('published'));

    $document = $snapshot->documents()->where('figure_count', '>', 0)->firstOrFail();

    // The link is on the page a reader actually lands on. A provenance route
    // nothing points at is a route nobody uses.
    $this->get(route('public.evidence.show', $document))
        ->assertOk()
        ->assertSee('see it on the page')
        ->assertSee(route('public.evidence.figure.page', $snapshot->figures()->firstOrFail()), escape: false);
});

it('says the figures are shown as they appear on the page', function (): void {
    ['page' => $page] = publishableDocument();
    certifiedFieldOn($page, 'accepted');

    $builder = app(PublicationSnapshotBuilder::class);
    $snapshot = $builder->publish($builder->build('published'));

    $this->get(route('public.evidence.show', $snapshot->documents()->firstOrFail()))
        ->assertOk()
        ->assertSee('it is wrong, and that is the point of showing it');
});
