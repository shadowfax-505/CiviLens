<?php

use App\Models\ExtractionPage;
use App\Services\Extraction\SecondReadScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Records which pages the command chose, without rasterising anything. */
function watchingScorer(array &$seen): SecondReadScorer
{
    return new class($seen) extends SecondReadScorer
    {
        /** @param  list<int>  $seen */
        public function __construct(private array &$seen) {}

        public function scorePage(ExtractionPage $page): array
        {
            $this->seen[] = (int) $page->getKey();

            return ['scored' => 0, 'unlocatable' => 0];
        }
    };
}

/** A page whose every value already carries a current score. */
function alreadyScoredPage(): void
{
    ['page' => $page] = publishableDocument();

    certifiedFieldOn($page, 'accepted', [
        'score_basis' => 'second-read',
        'nonconformity_score' => 0.1,
        'score_reader_version' => SecondReadScorer::READER_VERSION,
    ]);
}

it('stops when every candidate already carries a current score', function (): void {
    // A page whose score is current re-rasterises, re-reads every crop on it
    // and writes back the identical answer. Twice in one day a run reached zero
    // unscored and zero stale and kept going — the second time with 23,866 such
    // pages left, about four hours of CPU for no change to any row. From the
    // outside a scorer walking pages looks exactly like one doing work.
    $seen = [];

    foreach (range(1, 5) as $ignored) {
        alreadyScoredPage();
    }

    app()->instance(SecondReadScorer::class, watchingScorer($seen));

    $this->artisan('civiclens:score-fields', ['--sync' => true, '--limit' => 50])
        ->expectsOutputToContain('Every candidate carries a current score')
        ->assertExitCode(0);

    expect($seen)->toBeEmpty();
});

it('scores them again when asked to', function (): void {
    // A full walk is what an extraction change needs. The difference is that it
    // is now something you ask for.
    $seen = [];

    foreach (range(1, 5) as $ignored) {
        alreadyScoredPage();
    }

    app()->instance(SecondReadScorer::class, watchingScorer($seen));

    $this->artisan('civiclens:score-fields', ['--sync' => true, '--limit' => 50, '--all' => true])
        ->assertExitCode(0);

    expect($seen)->toHaveCount(5);
});

it('still scores a page nothing has scored yet', function (): void {
    $seen = [];

    alreadyScoredPage();

    ['page' => $fresh] = publishableDocument();
    certifiedFieldOn($fresh, 'pending', ['score_basis' => null, 'nonconformity_score' => null]);

    app()->instance(SecondReadScorer::class, watchingScorer($seen));

    $this->artisan('civiclens:score-fields', ['--sync' => true, '--limit' => 50])->assertExitCode(0);

    expect($seen)->toBe([(int) $fresh->id]);
});

it('still scores a page an earlier reader left behind', function (): void {
    // The stale path is the one that must never become opt-in: a score from an
    // earlier reader describes a reader that no longer exists.
    $seen = [];

    ['page' => $page] = publishableDocument();
    certifiedFieldOn($page, 'accepted', [
        'score_basis' => 'second-read',
        'nonconformity_score' => 0.1,
        'score_reader_version' => 'r1',
    ]);

    app()->instance(SecondReadScorer::class, watchingScorer($seen));

    $this->artisan('civiclens:score-fields', ['--sync' => true, '--limit' => 50])->assertExitCode(0);

    expect($seen)->toBe([(int) $page->id]);
});
