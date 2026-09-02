<?php

use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\SourceArtifactVersion;
use App\Services\Extraction\ArtifactWorkspace;
use App\Services\Extraction\SecondReadScorer;
use App\Services\Extraction\WordDocumentConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Counts the copies, and hands back a file that is really there. */
final class CountingWorkspace extends ArtifactWorkspace
{
    public int $copies = 0;

    public array $discarded = [];

    public function materialize(SourceArtifactVersion $artifact): string
    {
        $this->copies++;

        $path = tempnam(sys_get_temp_dir(), 'civiclens-test-');
        file_put_contents($path, 'not a pdf');

        return $path;
    }

    public function discard(?string $path): void
    {
        if ($path !== null) {
            $this->discarded[] = $path;
            @unlink($path);
        }
    }
}

function twoPagesOfOneDocument(): array
{
    ['page' => $first] = publishableDocument();

    $second = ExtractionPage::factory()->create([
        'extraction_run_id' => $first->extraction_run_id,
        'page_number' => (int) $first->page_number + 1,
    ]);

    foreach ([$first, $second] as $page) {
        ExtractionField::factory()->create([
            'extraction_run_id' => $page->extraction_run_id,
            'extraction_page_id' => $page->id,
        ]);
    }

    return [$first, $second];
}

it('copies the document once for two of its pages', function (): void {
    // Rasterising needs the file on local disk, so every page used to copy the
    // whole document again — 28.1 GB over the pages waiting to be re-scored,
    // against 0.7 GB copied once per document.
    $workspace = new CountingWorkspace(app(WordDocumentConverter::class));
    app()->instance(ArtifactWorkspace::class, $workspace);

    [$first, $second] = twoPagesOfOneDocument();
    $scorer = app(SecondReadScorer::class);

    $scorer->scorePage($first);
    $scorer->scorePage($second);

    expect($workspace->copies)->toBe(1);
});

it('lets go of the copy when the walk ends', function (): void {
    // A copy kept past the last page is a file nobody deletes, and a queue
    // worker keeps this object between jobs.
    $workspace = new CountingWorkspace(app(WordDocumentConverter::class));
    app()->instance(ArtifactWorkspace::class, $workspace);

    [$first] = twoPagesOfOneDocument();
    $scorer = app(SecondReadScorer::class);
    $scorer->scorePage($first);

    expect($workspace->discarded)->toBeEmpty();

    $scorer->release();

    expect($workspace->discarded)->toHaveCount(1)
        ->and(file_exists($workspace->discarded[0]))->toBeFalse();
});

it('copies again for a different document', function (): void {
    $workspace = new CountingWorkspace(app(WordDocumentConverter::class));
    app()->instance(ArtifactWorkspace::class, $workspace);

    ['page' => $mine] = publishableDocument();
    ['page' => $theirs] = publishableDocument();

    foreach ([$mine, $theirs] as $page) {
        ExtractionField::factory()->create([
            'extraction_run_id' => $page->extraction_run_id,
            'extraction_page_id' => $page->id,
        ]);
    }

    $scorer = app(SecondReadScorer::class);
    $scorer->scorePage($mine);
    $scorer->scorePage($theirs);

    expect($workspace->copies)->toBe(2);
});
