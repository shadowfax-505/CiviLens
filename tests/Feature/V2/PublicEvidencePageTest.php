<?php

use App\Models\PublicationSnapshot;
use App\Services\Publication\PublicationSnapshotBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('says what it is waiting for before anything is published', function (): void {
    // A site that 404s before its first snapshot looks broken. One that names
    // the missing step is telling the truth.
    $this->get(route('public.evidence.index'))
        ->assertOk()
        ->assertSee('Nothing has been published yet');
});

it('shows a certified figure with the level it was certified at', function (): void {
    // A bare count would invite the reader to treat every figure as equally
    // guaranteed. The level and its basis are part of the claim, so they are
    // on the page with it.
    ['page' => $page] = publishableDocument();
    certifiedFieldOn($page, 'accepted', ['extracted_value' => '৩,৪৫,৬৭৮']);

    $builder = app(PublicationSnapshotBuilder::class);
    $snapshot = $builder->publish($builder->build('published'));

    $document = $snapshot->documents()->where('figure_count', '>', 0)->firstOrFail();

    $this->get(route('public.evidence.show', $document))
        ->assertOk()
        ->assertSee('৩,৪৫,৬৭৮')
        ->assertSee('0.05')
        ->assertSee('conditional on');
});

it('never shows a value the system declined to stand behind', function (): void {
    ['page' => $page] = publishableDocument();
    certifiedFieldOn($page, 'accepted', ['extracted_value' => 'CERTIFIED-VALUE']);
    certifiedFieldOn($page, 'deferred', ['extracted_value' => 'DEFERRED-VALUE']);

    $builder = app(PublicationSnapshotBuilder::class);
    $snapshot = $builder->publish($builder->build('published'));

    $document = $snapshot->documents()->firstOrFail();

    $this->get(route('public.evidence.show', $document))
        ->assertOk()
        ->assertSee('CERTIFIED-VALUE')
        ->assertDontSee('DEFERRED-VALUE');
});

it('serves the published snapshot and not one that was only built', function (): void {
    ['page' => $page] = publishableDocument();
    certifiedFieldOn($page, 'accepted');

    $builder = app(PublicationSnapshotBuilder::class);
    $published = $builder->publish($builder->build('published'));
    $builder->build('draft');

    $this->get(route('public.evidence.index'))
        ->assertOk()
        ->assertSee('published')
        ->assertDontSee('draft');

    expect(PublicationSnapshot::current()->id)->toBe($published->id);
});

it('does not serve a document from a superseded snapshot', function (): void {
    // Old snapshots stay in the database so a citation resolves. Only one of
    // them is what the site is currently saying.
    ['page' => $page] = publishableDocument();
    certifiedFieldOn($page, 'accepted');

    $builder = app(PublicationSnapshotBuilder::class);
    $first = $builder->publish($builder->build('first'));
    $builder->publish($builder->build('second'));

    $this->get(route('public.evidence.show', $first->documents()->firstOrFail()))
        ->assertNotFound();
});

it('says a tender notice is a listing row rather than a contract record', function (): void {
    // ADR-020: e-GP answers with a listing that has no buyer, no supplier and
    // no value. A page that let a reader read it as a contract would be
    // overclaiming on the publisher's behalf.
    $builder = app(PublicationSnapshotBuilder::class);
    $builder->publish($builder->build('published'));

    $this->get(route('public.evidence.notices'))
        ->assertOk()
        ->assertSee('no buyer, no supplier')
        ->assertSee('none of it is certified');
});

it('says the guarantee is about the reading rather than the meaning', function (): void {
    // 1,131 of 5,186 accepted values on the real corpus are section numbers
    // like ২.২ off a contents page. They were read correctly, so the bound
    // holds; calling them amounts without saying what that claims would let a
    // reader treat a paragraph reference as money.
    ['page' => $page] = publishableDocument();
    certifiedFieldOn($page, 'accepted');

    $builder = app(PublicationSnapshotBuilder::class);
    $snapshot = $builder->publish($builder->build('published'));

    $this->get(route('public.evidence.show', $snapshot->documents()->firstOrFail()))
        ->assertOk()
        ->assertSee('about the reading, not the meaning')
        ->assertSee('certified as read');
});

it('shows what the pattern claimed a value is, not how it is stored', function (): void {
    // "string" is true of every value and tells a reader nothing.
    ['page' => $page] = publishableDocument();
    certifiedFieldOn($page, 'accepted', ['field_key' => 'amount', 'field_type' => 'string']);

    $builder = app(PublicationSnapshotBuilder::class);
    $snapshot = $builder->publish($builder->build('published'));

    expect($snapshot->figures()->first()->field_kind)->toBe('amount');
});
