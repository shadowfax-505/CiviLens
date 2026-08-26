<?php

use App\Models\ExtractionPage;
use App\Services\Extraction\PipelineHealthReport;
use App\Services\Extraction\SecondReadScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Records which pages the command chose, without rasterising anything. */
function recordingScorer(array &$seen): SecondReadScorer
{
    return new class($seen) extends SecondReadScorer
    {
        /** @param list<int> $seen */
        public function __construct(private array &$seen) {}

        public function scorePage(ExtractionPage $page): array
        {
            $this->seen[] = (int) $page->getKey();

            return ['scored' => 0, 'unlocatable' => 0];
        }
    };
}

it('reaches a page read today before rescoring pages that already have scores', function (): void {
    // Ordering by id alone made the newest work the least reachable: a page
    // read today sat behind every page ever read, and no limit short of the
    // whole corpus arrived at it. Twenty-five values from newly converted
    // documents stayed unscored through a thousand-page run.
    foreach (range(1, 4) as $ignored) {
        ['page' => $old] = publishableDocument();
        certifiedFieldOn($old, 'accepted', ['score_basis' => 'second-read', 'nonconformity_score' => 0.1]);
    }

    ['page' => $fresh] = publishableDocument();
    certifiedFieldOn($fresh, 'pending', ['score_basis' => null, 'nonconformity_score' => null]);

    expect($fresh->id)->toBeGreaterThan(4);

    $seen = [];
    $this->instance(SecondReadScorer::class, recordingScorer($seen));

    $this->artisan('civiclens:score-fields', ['--limit' => 1, '--sync' => true])->assertExitCode(0);

    expect($seen)->toBe([$fresh->id]);
});

it('still walks the corpus in order once nothing is unscored', function (): void {
    // A full re-score after an extraction change must behave as it always did.
    $ids = [];

    foreach (range(1, 3) as $ignored) {
        ['page' => $page] = publishableDocument();
        certifiedFieldOn($page, 'accepted', ['score_basis' => 'second-read', 'nonconformity_score' => 0.1]);
        $ids[] = $page->id;
    }

    sort($ids);

    $seen = [];
    $this->instance(SecondReadScorer::class, recordingScorer($seen));

    $this->artisan('civiclens:score-fields', ['--limit' => 3, '--sync' => true])->assertExitCode(0);

    expect($seen)->toBe($ids);
});

it('rescores a page whose score came from an earlier reader before one already current', function (): void {
    // A score says two readings of the same pixels agreed, and what counts as
    // agreement changed when the locator learned currency terminators and
    // cell-aware cropping. Scores from before that describe a reader that no
    // longer exists — they defer rather than over-accept, which is why nothing
    // broke and why 2,611 values sat marked unlocatable by a locator that had
    // since been taught to find them.
    ['page' => $current] = publishableDocument();
    certifiedFieldOn($current, 'accepted', [
        'score_basis' => 'second-read',
        'nonconformity_score' => 0.1,
        'score_reader_version' => SecondReadScorer::READER_VERSION,
    ]);

    ['page' => $stale] = publishableDocument();
    certifiedFieldOn($stale, 'accepted', [
        'score_basis' => 'unlocatable',
        'nonconformity_score' => null,
        'score_reader_version' => null,
    ]);

    expect($stale->id)->toBeGreaterThan($current->id);

    $seen = [];
    $this->instance(SecondReadScorer::class, recordingScorer($seen));

    $this->artisan('civiclens:score-fields', ['--limit' => 1, '--sync' => true])->assertExitCode(0);

    expect($seen)->toBe([$stale->id]);
});

it('reports every reader present so a half-rescored corpus cannot look finished', function (): void {
    ['page' => $page] = publishableDocument();
    certifiedFieldOn($page, 'accepted', [
        'score_basis' => 'second-read',
        'score_reader_version' => SecondReadScorer::READER_VERSION,
    ]);
    certifiedFieldOn($page, 'accepted', [
        'score_basis' => 'unlocatable',
        'score_reader_version' => null,
    ]);

    $scoring = app(PipelineHealthReport::class)->build()['checks']['scoring'];

    expect($scoring['stale'])->toBe(1)
        ->and($scoring['readers'])->toHaveKey('before-this-was-recorded')
        ->and($scoring['readers'])->toHaveKey(SecondReadScorer::READER_VERSION)
        // Behind is not broken. A stale score defers, so failing here would cry
        // wolf on a corpus that is merely owed work.
        ->and($scoring['ok'])->toBeTrue()
        ->and($scoring['detail'])->toContain('owed a re-score');
});
