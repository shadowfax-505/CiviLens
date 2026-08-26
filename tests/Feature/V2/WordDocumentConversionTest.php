<?php

use App\Models\SourceArtifactVersion;
use App\Services\Extraction\ArtifactWorkspace;
use App\Services\Extraction\WordDocumentConverter;
use App\Services\Publication\PublicationSnapshotBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * A minimal but genuinely valid .docx, built rather than committed.
 *
 * A fixture that is not a real Word file would prove only that our own stub
 * reader accepts our own stub, which is the failure mode this whole exercise
 * exists to avoid.
 */
function miniWordDocument(string $token = 'CONVERSION-PROOF-TOKEN'): string
{
    $path = tempnam(sys_get_temp_dir(), 'civiclens-test-docx-');
    @unlink($path);

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
    $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'.$token.'</w:t></w:r></w:p></w:body></w:document>');
    $zip->close();

    return $path;
}

it('hands a word document to the pipeline as a readable pdf', function (): void {
    // Before this, 13 acquired IMED documents were never read: everything
    // downstream rasterises the path materialize() returns, and a .docx has no
    // pages to rasterise. The conversion is what makes them corpus.
    if (! app(WordDocumentConverter::class)->isAvailable()) {
        $this->markTestSkipped('LibreOffice is not installed on this machine.');
    }

    Storage::fake('local');

    $source = miniWordDocument();
    Storage::disk('local')->put('artifacts/report.docx', (string) file_get_contents($source));
    @unlink($source);

    $artifact = SourceArtifactVersion::factory()->create([
        'storage_disk' => 'local',
        'storage_path' => 'artifacts/report.docx',
        'media_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'byte_size' => 922,
        'is_quarantined' => false,
    ]);

    $workspace = app(ArtifactWorkspace::class);
    $path = $workspace->materialize($artifact);

    expect(substr((string) file_get_contents($path), 0, 5))->toBe('%PDF-');

    // And the dispatch that picks an extractor must agree, or the file is
    // looked up under the format it arrived in and refused.
    expect($workspace->effectiveMediaType($artifact))->toBe('application/pdf');

    $workspace->discard($path);
});

it('converts once and reuses the rendering', function (): void {
    if (! app(WordDocumentConverter::class)->isAvailable()) {
        $this->markTestSkipped('LibreOffice is not installed on this machine.');
    }

    Storage::fake('local');

    $source = miniWordDocument();
    Storage::disk('local')->put('artifacts/report.docx', (string) file_get_contents($source));
    @unlink($source);

    $artifact = SourceArtifactVersion::factory()->create([
        'storage_disk' => 'local',
        'storage_path' => 'artifacts/report.docx',
        'media_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'byte_size' => 922,
        'is_quarantined' => false,
    ]);

    $workspace = app(ArtifactWorkspace::class);
    $workspace->discard($workspace->materialize($artifact));

    // Materialising happens once per page per pass, across five passes. A
    // conversion per call would cost more than the reading does.
    Storage::disk('local')->assertExists('converted-pdf/'.$artifact->sha256.'.pdf');

    $workspace->discard($workspace->materialize($artifact));
});

it('leaves a pdf artifact untouched', function (): void {
    // The conversion must be invisible to everything that was already working.
    Storage::fake('local');
    Storage::disk('local')->put('artifacts/report.pdf', '%PDF-1.4 original bytes');

    $artifact = SourceArtifactVersion::factory()->create([
        'storage_disk' => 'local',
        'storage_path' => 'artifacts/report.pdf',
        'media_type' => 'application/pdf',
        'byte_size' => 23,
        'is_quarantined' => false,
    ]);

    $workspace = app(ArtifactWorkspace::class);
    $path = $workspace->materialize($artifact);

    expect(file_get_contents($path))->toBe('%PDF-1.4 original bytes')
        ->and($workspace->effectiveMediaType($artifact))->toBe('application/pdf');

    $workspace->discard($path);
});

it('tells a reader when the page images are our rendering rather than the publisher\'s', function (): void {
    // "You can go and look" is the whole promise of the evidence page. For a
    // converted document the layout a reader sees is ours, and saying so is
    // what keeps that promise honest.
    ['page' => $page] = publishableDocument('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    certifiedFieldOn($page, 'accepted', ['extracted_value' => '৩,৪৫,৬৭৮']);

    $builder = app(PublicationSnapshotBuilder::class);
    $snapshot = $builder->publish($builder->build('published'));

    $document = $snapshot->documents()->firstOrFail();

    expect($document->pageImagesAreConverted())->toBeTrue();

    $this->get(route('public.evidence.show', $document))
        ->assertOk()
        ->assertSee('our rendering, not the publisher');
});

it('does not claim a pdf was re-rendered', function (): void {
    ['page' => $page] = publishableDocument();
    certifiedFieldOn($page, 'accepted', ['extracted_value' => '৩,৪৫,৬৭৮']);

    $builder = app(PublicationSnapshotBuilder::class);
    $snapshot = $builder->publish($builder->build('published'));

    $document = $snapshot->documents()->firstOrFail();

    expect($document->pageImagesAreConverted())->toBeFalse();

    $this->get(route('public.evidence.show', $document))
        ->assertOk()
        ->assertDontSee('our rendering, not the publisher');
});
