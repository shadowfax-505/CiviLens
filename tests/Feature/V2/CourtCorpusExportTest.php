<?php

use App\Models\DiscoveredResource;
use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\ExtractionRun;
use App\Models\SourceArtifactVersion;
use App\Models\SourceEndpoint;
use App\Models\SourcePublisher;
use App\Services\Corpus\CourtCorpusExport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** The court publisher and one endpoint under it, which is what scopes the export. */
function courtEndpoint(): SourceEndpoint
{
    $publisher = SourcePublisher::factory()->create([
        'slug' => 'supremecourt-bangladesh',
        'name' => 'Supreme Court of Bangladesh',
        'attribution_name' => 'Supreme Court of Bangladesh',
    ]);

    return SourceEndpoint::factory()->create([
        'source_publisher_id' => $publisher->id,
        'name' => 'HCD judgments',
    ]);
}

/**
 * A judgment: an artifact under the endpoint, a completed run, and its pages.
 *
 * @return array{artifact: SourceArtifactVersion, run: ExtractionRun}
 */
function judgment(SourceEndpoint $endpoint, string $sha256, int $pages = 2, bool $quarantined = false): array
{
    $artifact = SourceArtifactVersion::factory()->create([
        'discovered_resource_id' => DiscoveredResource::factory()->create([
            'source_endpoint_id' => $endpoint->id,
        ])->id,
        'sha256' => $sha256,
        // Set at creation, because the factory quarantines by default and an
        // artifact is quarantined when its scan fails rather than afterwards.
        'is_quarantined' => $quarantined,
    ]);

    $run = ExtractionRun::factory()->create([
        'source_artifact_version_id' => $artifact->id,
        'status' => 'completed',
        'page_count' => $pages,
    ]);

    foreach (range(1, $pages) as $number) {
        ExtractionPage::factory()->create([
            'extraction_run_id' => $run->id,
            'page_number' => $number,
            'extracted_text' => 'page '.$number.' of '.substr($sha256, 0, 6),
        ]);
    }

    return ['artifact' => $artifact, 'run' => $run];
}

function courtValue(ExtractionRun $run, string $kind, string $value, string $decision): ExtractionField
{
    return ExtractionField::factory()->create([
        'extraction_run_id' => $run->id,
        'extraction_page_id' => ExtractionPage::query()->where('extraction_run_id', $run->id)->value('id'),
        'field_key' => $kind,
        'extracted_value' => $value,
        'publisher_group' => 'supremecourt-bangladesh',
        'script_class' => 'en',
        'decision' => $decision,
        'decision_alpha' => $decision === 'accepted' ? 0.05 : null,
        'decision_basis' => $decision === 'accepted' ? 'stratum' : null,
        'nonconformity_score' => 0.01,
    ]);
}

/** @return list<array<string, mixed>> */
function exportedRecords(string $directory, string $split): array
{
    $path = $directory.'/'.$split.'.jsonl';

    if (! is_file($path)) {
        return [];
    }

    $lines = array_filter(explode("\n", (string) file_get_contents($path)));

    return array_map(fn (string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR), $lines);
}

function exportInto(int $holdout = 20): array
{
    $directory = sys_get_temp_dir().'/civiclens-corpus-'.bin2hex(random_bytes(6));

    $manifest = app(CourtCorpusExport::class)->export($directory, $holdout);

    return [$directory, $manifest];
}

it('exports one record per judgment, with its text in page order', function (): void {
    $endpoint = courtEndpoint();
    judgment($endpoint, str_repeat('a', 64), pages: 3);

    [$directory] = exportInto(holdout: 0);

    $records = exportedRecords($directory, CourtCorpusExport::TRAIN);

    expect($records)->toHaveCount(1)
        ->and($records[0]['page_count'])->toBe(3)
        ->and($records[0]['text'])->toBe("page 1 of aaaaaa\n\npage 2 of aaaaaa\n\npage 3 of aaaaaa")
        ->and($records[0]['publisher_group'])->toBe('supremecourt-bangladesh');
});

it('exports a judgment held at two addresses once', function (): void {
    // 2,525 court artifacts hold 2,507 files: the same judgment is linked from
    // a listing page and from the court's own document store, and discovery
    // follows both. A training set holding one judgment twice has a holdout
    // that overlaps its own training data.
    $endpoint = courtEndpoint();
    $sha = str_repeat('b', 64);

    judgment($endpoint, $sha);
    judgment($endpoint, $sha);

    [$directory] = exportInto(holdout: 0);

    expect(exportedRecords($directory, CourtCorpusExport::TRAIN))->toHaveCount(1);
});

it('exports one set of pages for a document that was read twice', function (): void {
    // A document read twice has two complete sets of pages. Exporting both is
    // how one 587-page judgment came to be published as fifteen figures that
    // were eight values and seven of the same values again.
    $endpoint = courtEndpoint();
    ['artifact' => $artifact] = judgment($endpoint, str_repeat('c', 64), pages: 2);

    $second = ExtractionRun::factory()->create([
        'source_artifact_version_id' => $artifact->id,
        'status' => 'completed',
        'page_count' => 2,
    ]);

    foreach (range(1, 2) as $number) {
        ExtractionPage::factory()->create([
            'extraction_run_id' => $second->id,
            'page_number' => $number,
            'extracted_text' => 'the second reading, page '.$number,
        ]);
    }

    // And a later run that failed, which is the case row order cannot settle.
    // A failed run left its pages in whatever state it reached and they are not
    // pages of this corpus, so the newest run is the wrong answer and the
    // newest run that did not fail is the right one. Keying a map by artifact
    // hides a missing filter — the last row simply overwrites the first — so
    // without this the test passes whether or not the scope is applied.
    $abandoned = ExtractionRun::factory()->create([
        'source_artifact_version_id' => $artifact->id,
        'status' => 'failed',
        'page_count' => 1,
    ]);

    ExtractionPage::factory()->create([
        'extraction_run_id' => $abandoned->id,
        'page_number' => 1,
        'extracted_text' => 'the abandoned reading',
    ]);

    [$directory] = exportInto(holdout: 0);

    $records = exportedRecords($directory, CourtCorpusExport::TRAIN);

    expect($records)->toHaveCount(1)
        ->and($records[0]['page_count'])->toBe(2)
        ->and($records[0]['extraction_run_id'])->toBe($second->id)
        ->and($records[0]['text'])->toContain('the second reading')
        ->and($records[0]['text'])->not->toContain('the abandoned reading');
});

it('keeps a certified value apart from a candidate, with the level and the basis', function (): void {
    // Three of the five exported kinds have no threshold at all, so a flattened
    // list would hand a consumer 58,000 uncertified strings wearing a guarantee
    // that was never made about them.
    $endpoint = courtEndpoint();
    ['run' => $run] = judgment($endpoint, str_repeat('d', 64));

    courtValue($run, 'amount', '৩,৪৫,৬৭৮', 'accepted');
    courtValue($run, 'case_number', 'Writ Petition No. 8951 of 2025', 'deferred');

    [$directory] = exportInto(holdout: 0);

    $record = exportedRecords($directory, CourtCorpusExport::TRAIN)[0];

    expect($record['certified_values'])->toHaveCount(1)
        ->and($record['candidate_values'])->toHaveCount(1)
        ->and($record['certified_values'][0]['kind'])->toBe('amount')
        ->and($record['certified_values'][0]['certified'])->toBeTrue()
        ->and($record['certified_values'][0]['decision_alpha'])->toBe(0.05)
        ->and($record['certified_values'][0]['decision_basis'])->toBe('stratum')
        ->and($record['certified_values'][0]['stratum'])->toBe('supremecourt-bangladesh|en|amount')
        ->and($record['candidate_values'][0]['kind'])->toBe('case_number')
        ->and($record['candidate_values'][0]['certified'])->toBeFalse()
        ->and($record['candidate_values'][0]['decision_basis'])->toBeNull();
});

it('exports amounts, which the first version of this left out', function (): void {
    // The corpus trains a model that detects financial irregularity, and a sum
    // is what that needs as a value rather than as a substring of page text.
    expect(CourtCorpusExport::FIELD_KINDS)->toContain('amount');

    $endpoint = courtEndpoint();
    ['run' => $run] = judgment($endpoint, str_repeat('e', 64));

    courtValue($run, 'amount', '৯৯,৯৯৯', 'accepted');
    courtValue($run, 'organisation', 'Some Ministry', 'accepted');

    [$directory, $manifest] = exportInto(holdout: 0);

    $record = exportedRecords($directory, CourtCorpusExport::TRAIN)[0];

    // And organisation names are still left behind, counted rather than dropped.
    expect(array_column($record['certified_values'], 'kind'))->toBe(['amount'])
        ->and($manifest['field_kinds_present_but_not_exported'])->toBe(['organisation' => 1]);
});

it('puts a document in the same split every time, from its checksum alone', function (): void {
    // Derived rather than stored: a split recorded in a table can disagree with
    // itself after a rebuild, and a model evaluated against a holdout it was
    // trained on measures nothing.
    $sha = hash('sha256', 'a particular judgment');

    $first = CourtCorpusExport::splitOf($sha, 20);

    expect(CourtCorpusExport::splitOf($sha, 20))->toBe($first)
        ->and(CourtCorpusExport::splitOf($sha, 20))->toBe($first);

    // Nothing is held out at nought per cent, and everything is at a hundred.
    expect(CourtCorpusExport::splitOf($sha, 0))->toBe(CourtCorpusExport::TRAIN)
        ->and(CourtCorpusExport::splitOf($sha, 100))->toBe(CourtCorpusExport::HOLDOUT);
});

it('holds out roughly the percentage asked for', function (): void {
    $buckets = ['train' => 0, 'holdout' => 0];

    foreach (range(1, 400) as $index) {
        $buckets[CourtCorpusExport::splitOf(hash('sha256', 'judgment '.$index), 25)]++;
    }

    // 25% of 400 is 100. The bound is loose because this is a hash, not a
    // sample: it is checking the rule is applied, not that sha256 is uniform.
    expect($buckets['holdout'])->toBeGreaterThan(60)->toBeLessThan(140);
});

it('writes a manifest that says what was published, what was held out, and who is named', function (): void {
    $endpoint = courtEndpoint();
    ['run' => $run] = judgment($endpoint, str_repeat('f', 64));
    courtValue($run, 'date', '01.01.2025', 'accepted');

    [, $manifest] = exportInto(holdout: 20);

    expect($manifest)->toHaveKeys([
        'published_snapshot', 'holdout', 'documents', 'pages', 'values',
        'field_kinds', 'by_stratum', 'sources', 'personal_names', 'certification',
    ]);

    expect($manifest['holdout']['percentage'])->toBe(20)
        ->and($manifest['field_kinds_exported'])->toContain('amount')
        // The claim is about the reading, never the meaning, and the manifest
        // has to say so where a consumer will see it.
        ->and($manifest['personal_names'])->toContain('name private individuals')
        ->and($manifest['certification'])->toContain('about the reading');

    expect($manifest['sources'][0]['attribution_name'])->toBe('Supreme Court of Bangladesh');
});

it('does not export a quarantined artifact', function (): void {
    // Its bytes were never trusted enough to read, and exporting one the day
    // there is one would be worse than noticing a document is missing.
    $endpoint = courtEndpoint();
    judgment($endpoint, str_repeat('9', 64), quarantined: true);

    [$directory, $manifest] = exportInto(holdout: 0);

    expect(exportedRecords($directory, CourtCorpusExport::TRAIN))->toBeEmpty()
        ->and($manifest['documents']['exported'] ?? 0)->toBe(0);
});

it('never fetches the page or field table whole', function (): void {
    // Four out-of-memory failures in this pipeline were the same mistake, and
    // this corpus is 31,775 pages against 101,845 values. The failure mode is
    // ->get() or ->all() on a row-per-record query; an aggregate that groups in
    // the database returns one row per kind and is not the same thing, so this
    // looks for the former rather than demanding chunkById everywhere.
    $source = (string) file_get_contents(base_path('app/Services/Corpus/CourtCorpusExport.php'));

    foreach (['ExtractionPage::query()', 'ExtractionField::query()'] as $table) {
        $offset = 0;

        while (($at = strpos($source, $table, $offset)) !== false) {
            // To the end of the statement, so one query is examined at a time.
            $end = strpos($source, ';', $at);
            $statement = substr($source, $at, ($end === false ? strlen($source) : $end) - $at);

            $streamed = str_contains($statement, 'chunkById');
            $aggregated = str_contains($statement, 'selectRaw') && str_contains($statement, 'groupBy');

            expect($streamed || $aggregated)->toBeTrue(
                'This query walks rows without chunking them: '.substr($statement, 0, 120),
            );

            $offset = $at + strlen($table);
        }
    }
});
