<?php

namespace App\Services\Corpus;

use App\Models\DiscoveredResource;
use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\ExtractionRun;
use App\Models\PublicationSnapshot;
use App\Models\SourceArtifactVersion;
use App\Models\SourceEndpoint;
use App\Models\SourcePublisher;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * A training corpus of court judgments, written one document at a time.
 *
 * Everything here is shaped by two facts about this corpus that a naive export
 * gets wrong.
 *
 * **It is bigger than memory.** 2,507 judgments, 31,775 pages and 53 MB of page
 * text, against 101,845 extracted values. Four out-of-memory failures in this
 * pipeline were all the same mistake — a collection loaded whole — so nothing
 * here calls `->get()` on the page or field table, and the output is JSONL
 * written line by line rather than one array held until the end. Peak memory is
 * one document's worth, and the largest judgment in the corpus is 399 pages,
 * 1.3 MB of text and 2,497 values.
 *
 * **Most of what it holds is not certified.** Two of the five kinds exported
 * have a fitted threshold, and three have none at all. Measured over the
 * current runs of this publisher's canonical documents, certified against
 * everything else:
 *
 *     date                  32,148 / 26,865
 *     amount                 9,103 / 13,450
 *     case_number                0 / 5,867
 *     statutory_reference        0 / 7,780
 *     law_report_citation        0 / 5,231
 *
 * The three zeroes are not a failure of reading. The stratum is publisher ×
 * script × kind (ADR-018), and nobody has judged a case number, so no threshold
 * exists to apply to one. An export that flattened certified and candidate
 * together would hand a consumer 58,000 uncertified strings wearing a guarantee
 * that was never made about them — so they go into separate keys, and every
 * value carries the decision, the level and the basis that produced it.
 */
class CourtCorpusExport
{
    public const TRAIN = 'train';

    public const HOLDOUT = 'holdout';

    /**
     * The only decision that carries a conformal guarantee.
     *
     * `deferred` is the system declining to stand behind a value and `pending`
     * is a value no threshold has been applied to yet. Neither is a weaker
     * certification; both are the absence of one.
     */
    public const CERTIFIED_DECISION = 'accepted';

    /**
     * What a judgment says about itself, and what it says about money.
     *
     * How it is cited, when, under which provision, on the authority of which
     * reported decision — and the sums it discusses. The amounts were left out
     * of the first version of this export on the grounds that it was about the
     * case rather than the money in it, which was wrong for what this corpus is
     * for: the model being trained on it detects financial irregularity, and a
     * sum is exactly the thing it needs as a value rather than as a substring
     * somewhere in the page text.
     *
     * Bare `reference` (864 values) and `organisation` (537) stay out. A bare
     * reference is a number the extractor could not attribute to any of the
     * kinds above, so it carries no meaning to train on, and an organisation
     * name in a judgment is most often a party to it — which the full text
     * already gives, in the context that makes it interpretable. The manifest
     * counts both rather than dropping them silently, which is the discipline
     * ADR-020 set.
     */
    public const FIELD_KINDS = [
        'case_number', 'date', 'statutory_reference', 'law_report_citation', 'amount', 'disposition',
    ];

    /**
     * The kind that is context rather than a value, and can never be certified.
     *
     * A case number is uncertified today and certifiable tomorrow: judge
     * nineteen of them and its stratum gets a threshold. A finding span cannot
     * ever be, and not because nobody has judged one — it is a whole sentence,
     * kept past anything `ValueLocator` can span by a 17-word floor, so it can
     * never acquire a score to threshold. Both are true of it permanently and
     * by construction.
     *
     * Putting the two in one bucket would say the same thing about both, and
     * the difference is exactly what a consumer needs: a candidate is a value
     * awaiting a guarantee, and this is the court's own words carried for
     * reading. It gets its own key and no decision fields, because there is no
     * decision and never will be.
     */
    public const CITED_CONTEXT_KIND = 'finding_span';

    /** Judgments are one publisher's, and the slug is what scopes the export. */
    public const DEFAULT_PUBLISHER = 'supremecourt-bangladesh';

    /**
     * Documents per artifact chunk, which is also the run-lookup batch.
     *
     * Small enough that a chunk of artifacts and their current runs sit in
     * memory beside one document's pages without the total depending on how
     * large the corpus grows.
     */
    private const DOCUMENTS_PER_CHUNK = 100;

    /** Pages and values per streamed batch, within one document. */
    private const ROWS_PER_CHUNK = 500;

    /**
     * The note the manifest is required to carry.
     *
     * Judgments name petitioners, respondents, witnesses, counsel and judges,
     * and they do so because the court published them that way. Training on
     * that text is in scope. Asserting anything about a person named in it is
     * not, and this export builds no machinery that could: there is no scoring,
     * flagging or labelling of individuals anywhere in it.
     */
    public const PERSONAL_NAMES_NOTE = 'These documents name private individuals — petitioners, respondents, '
        .'witnesses, counsel and judges — because the court published them that way, and the text here is the '
        .'court\'s own. Nothing in this export scores, flags, ranks or labels any person, and no certified value '
        .'is a claim about anyone named on the page. A certified value means only that the system is confident it '
        .'read that string off the page correctly; it says nothing about what the string means, and nothing '
        .'whatever about the conduct of any person the document names.';

    /**
     * Write the corpus, and return the manifest that was written beside it.
     *
     * Read-only against the database by construction: every query here is a
     * select, and nothing in this class writes a row.
     *
     * @return array<string, mixed>
     */
    public function export(
        string $directory,
        int $holdoutPercentage = 20,
        string $publisherSlug = self::DEFAULT_PUBLISHER,
        int $limit = 0,
    ): array {
        if ($holdoutPercentage < 0 || $holdoutPercentage > 100) {
            throw new RuntimeException('The holdout percentage must be between 0 and 100.');
        }

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the export directory: '.$directory);
        }

        $publisher = SourcePublisher::query()->where('slug', $publisherSlug)->first();

        if (! $publisher instanceof SourcePublisher) {
            throw new RuntimeException('No publisher is registered under the slug '.$publisherSlug.'.');
        }

        // A plain map rather than a keyed collection. There are two endpoints
        // behind this publisher; both are wanted by id and by iteration, and an
        // array says that without a generic type that then has to be explained.
        $endpoints = [];

        foreach (SourceEndpoint::query()
            ->where('source_publisher_id', $publisher->getKey())
            ->get(['id', 'name', 'base_url', 'access_decision', 'robots_override_reason', 'plaintext_override_reason']) as $endpoint) {
            $endpoints[(int) $endpoint->getKey()] = $endpoint;
        }

        $writers = $this->openWriters($directory);
        $tally = new CourtCorpusTally;

        try {
            $this->streamDocuments($publisher, $endpoints, $holdoutPercentage, $limit, $writers, $tally);
        } finally {
            foreach ($writers as $handle) {
                fclose($handle);
            }
        }

        $manifest = $this->manifest($publisher, $endpoints, $holdoutPercentage, $limit, $tally);

        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        file_put_contents($directory.'/manifest.json', $json."\n");

        return $manifest;
    }

    /**
     * Which split a document belongs to, from its checksum alone.
     *
     * Derived rather than stored, and derived from the sha256 rather than drawn
     * at random, so the same judgment lands in the same split on every machine
     * and every re-export without anything having to remember the last one. A
     * split recorded in a table is a split that can disagree with itself after
     * a rebuild, and a model evaluated against a holdout it was trained on
     * measures nothing.
     *
     * The first eight hex digits are 32 bits, which modulo 100 leaves the first
     * 96 buckets larger than the last four by one part in fifty million. That
     * is orders of magnitude below the sampling noise in any split of 2,507
     * documents and is not worth a modular-rejection loop.
     */
    public static function splitOf(string $sha256, int $holdoutPercentage): string
    {
        if ($holdoutPercentage <= 0) {
            return self::TRAIN;
        }

        $bucket = (int) hexdec(substr($sha256, 0, 8)) % 100;

        return $bucket < $holdoutPercentage ? self::HOLDOUT : self::TRAIN;
    }

    /** Whether a value carries a conformal guarantee, which only one decision does. */
    public static function isCertified(?string $decision): bool
    {
        return $decision === self::CERTIFIED_DECISION;
    }

    /**
     * The stratum a guarantee is conditional on: publisher, script and kind.
     *
     * All three, because ADR-018's second addendum made the stratum
     * publisher × script × kind, and a summary keyed on anything coarser states
     * a wider claim than the one that was certified.
     */
    public static function stratumOf(?string $publisherGroup, ?string $scriptClass, ?string $kind): string
    {
        return implode('|', [
            $publisherGroup ?? 'unattributed',
            $scriptClass ?? 'unknown',
            $kind ?? 'unknown',
        ]);
    }

    /**
     * @return array<string, resource>
     */
    private function openWriters(string $directory): array
    {
        $writers = [];

        foreach ([self::TRAIN, self::HOLDOUT] as $split) {
            $handle = fopen($directory.'/'.$split.'.jsonl', 'wb');

            if ($handle === false) {
                foreach ($writers as $open) {
                    fclose($open);
                }

                throw new RuntimeException('Unable to write into the export directory: '.$directory);
            }

            $writers[$split] = $handle;
        }

        return $writers;
    }

    /**
     * @param  array<int, SourceEndpoint>  $endpoints
     * @param  array<string, resource>  $writers
     */
    private function streamDocuments(
        SourcePublisher $publisher,
        array $endpoints,
        int $holdoutPercentage,
        int $limit,
        array $writers,
        CourtCorpusTally $tally,
    ): void {
        $written = 0;

        SourceArtifactVersion::query()
            // One row per distinct file. 2,525 court artifacts hold 2,507
            // files: the same judgment is linked from a listing page and from
            // the court's own document store, and discovery follows both. A
            // duplicated document duplicates its pages and its values, and a
            // training set holding one judgment twice has a holdout that
            // overlaps its own training data.
            ->canonical()
            // A quarantined artifact failed its malware scan and its bytes were
            // never trusted enough to read. There are none in the court corpus
            // today, and exporting one the day there is would be worse than
            // noticing that a document is missing.
            ->where('is_quarantined', false)
            ->whereIn('discovered_resource_id', DiscoveredResource::query()
                ->whereIn('source_endpoint_id', array_keys($endpoints))
                ->select('id'))
            ->select(['id', 'discovered_resource_id', 'sha256', 'media_type', 'byte_size', 'original_filename', 'retrieval_url', 'retrieved_at'])
            ->with(['discoveredResource:id,source_endpoint_id,canonical_url'])
            ->chunkById(self::DOCUMENTS_PER_CHUNK, function (Collection $artifacts) use (
                $publisher,
                $endpoints,
                $holdoutPercentage,
                $limit,
                $writers,
                $tally,
                &$written,
            ): bool {
                $artifactIds = [];

                foreach ($artifacts as $artifact) {
                    $artifactIds[] = (int) $artifact->getKey();
                }

                $runs = $this->currentRunsFor($artifactIds);

                foreach ($artifacts as $artifact) {
                    if ($limit > 0 && $written >= $limit) {
                        return false;
                    }

                    $run = $runs[(int) $artifact->getKey()] ?? null;

                    // A document nothing has read yet is not part of a text
                    // corpus. Counted, so the shortfall is visible: a silent
                    // skip here would make the export look like the archive.
                    if (! $run instanceof ExtractionRun) {
                        $tally->documentWithoutARun();

                        continue;
                    }

                    $this->writeDocument($artifact, $run, $publisher, $endpoints, $holdoutPercentage, $writers, $tally);
                    $written++;
                }

                return true;
            });
    }

    /**
     * @param  array<int, SourceEndpoint>  $endpoints
     * @param  array<string, resource>  $writers
     */
    private function writeDocument(
        SourceArtifactVersion $artifact,
        ExtractionRun $run,
        SourcePublisher $publisher,
        array $endpoints,
        int $holdoutPercentage,
        array $writers,
        CourtCorpusTally $tally,
    ): void {
        $sha256 = (string) $artifact->sha256;
        $split = self::splitOf($sha256, $holdoutPercentage);
        $resource = $artifact->discoveredResource;
        $endpoint = $resource instanceof DiscoveredResource
            ? ($endpoints[(int) $resource->source_endpoint_id] ?? null)
            : null;
        $endpointName = $endpoint instanceof SourceEndpoint ? (string) $endpoint->name : null;

        $pages = $this->pagesOf((int) $run->getKey());
        $values = $this->valuesOf((int) $run->getKey());

        $record = [
            'sha256' => $sha256,
            'split' => $split,
            'publisher_group' => (string) $publisher->slug,
            'publisher_name' => (string) $publisher->name,
            'attribution_name' => $publisher->attribution_name,
            'source_endpoint' => $endpointName,
            'source_url' => (string) ($artifact->retrieval_url ?: ($resource instanceof DiscoveredResource ? $resource->canonical_url : '')),
            'media_type' => $artifact->media_type,
            'byte_size' => $artifact->byte_size,
            'original_filename' => $artifact->original_filename,
            'retrieved_at' => $this->iso($artifact->retrieved_at),
            // The pages actually read, not the run's own tally. The two agree
            // across this corpus and they are not the same claim: `page_count`
            // is what the rasteriser reported, and this is what the export
            // contains. A consumer counting tokens needs the second.
            'page_count' => count($pages['pages']),
            'reported_page_count' => $run->page_count,
            'pages_by_script' => $pages['scripts'],
            'extraction_run_id' => (int) $run->getKey(),
            'text' => $pages['text'],
            'pages' => $pages['pages'],
            // Two keys rather than one list with a flag. A consumer that reads
            // `certified_values` cannot accidentally pick up a candidate, and
            // one that concatenates both still has the decision on every row.
            // Only `date` reaches a threshold in this corpus, so a flattened
            // list would be four fifths uncertified.
            'certified_values' => $values['certified'],
            'candidate_values' => $values['candidates'],
            // Neither certified nor awaiting certification. See CITED_CONTEXT_KIND.
            'cited_context' => $values['context'],
        ];

        $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        fwrite($writers[$split], $line."\n");

        $tally->document($split, $endpointName, $pages, $values);
    }

    /**
     * The runs that currently describe these documents, and only those.
     *
     * A document read twice has two complete sets of pages and values, and this
     * corpus holds 3,951 runs over 3,291 documents. Exporting all of them is
     * how one 587-page judgment came to be published as fifteen figures that
     * were eight values and seven of the same values again — and in a training
     * corpus the same mistake duplicates page text as well.
     *
     * @param  list<int>  $artifactIds
     * @return array<int, ExtractionRun>
     */
    private function currentRunsFor(array $artifactIds): array
    {
        $runs = [];

        foreach (ExtractionRun::query()
            ->whereIn('id', ExtractionRun::currentIds())
            ->whereIn('source_artifact_version_id', $artifactIds)
            ->get(['id', 'source_artifact_version_id', 'page_count']) as $run) {
            $runs[(int) $run->source_artifact_version_id] = $run;
        }

        return $runs;
    }

    /**
     * One document's page text, in page order.
     *
     * Streamed in batches even though a single judgment fits in memory, because
     * the cost of getting this wrong is paid over the whole corpus rather than
     * over one document.
     *
     * `chunkById` orders by id and overrides any ordering asked of it, so the
     * text is reassembled by page number afterwards rather than on the
     * assumption that the two agree.
     *
     * @return array{text: string, pages: list<array<string, mixed>>, scripts: array<string, int>}
     */
    private function pagesOf(int $runId): array
    {
        $text = [];
        $pages = [];
        $scripts = [];

        ExtractionPage::query()
            ->where('extraction_run_id', $runId)
            ->select(['id', 'page_number', 'script_class', 'extraction_path', 'character_count', 'extracted_text'])
            ->chunkById(self::ROWS_PER_CHUNK, function (Collection $rows) use (&$text, &$pages, &$scripts): void {
                foreach ($rows as $page) {
                    $number = (int) $page->page_number;
                    $script = $page->script_class === null ? 'unknown' : (string) $page->script_class;

                    $text[$number] = (string) $page->extracted_text;
                    $scripts[$script] = ($scripts[$script] ?? 0) + 1;
                    $pages[$number] = [
                        'page_number' => $number,
                        'script_class' => $page->script_class,
                        // An abstained page stored no text at all, so a page
                        // holding an empty string here is one the recognizer
                        // declined rather than a blank page in the document.
                        // Saying which is the difference between a gap and a
                        // silence.
                        'extraction_path' => $page->extraction_path,
                        'character_count' => $page->character_count,
                    ];
                }
            });

        ksort($text);
        ksort($pages);
        ksort($scripts);

        return [
            'text' => implode("\n\n", $text),
            'pages' => array_values($pages),
            'scripts' => $scripts,
        ];
    }

    /**
     * One document's extracted values, split by whether they are certified.
     *
     * The counts come back beside the values because the caller needs both and
     * recounting a list of arrays means narrowing every element again.
     *
     * @return array{
     *     certified: list<array<string, mixed>>,
     *     candidates: list<array<string, mixed>>,
     *     context: list<array<string, mixed>>,
     *     kinds: array<string, array{certified: int, candidate: int}>,
     *     strata: array<string, array{certified: int, candidate: int}>,
     * }
     */
    private function valuesOf(int $runId): array
    {
        $certified = [];
        $candidates = [];
        $context = [];
        $kinds = [];
        $strata = [];

        ExtractionField::query()
            ->where('extraction_run_id', $runId)
            ->whereIn('field_key', [...self::FIELD_KINDS, self::CITED_CONTEXT_KIND])
            ->select([
                'id', 'field_key', 'extracted_value', 'normalized_value', 'evidence_page_number',
                'script_class', 'publisher_group', 'decision', 'decision_alpha', 'decision_basis',
                'nonconformity_score', 'score_basis',
            ])
            ->chunkById(self::ROWS_PER_CHUNK, function (Collection $rows) use (&$certified, &$candidates, &$context, &$kinds, &$strata): void {
                foreach ($rows as $field) {
                    $decision = $field->decision === null ? null : (string) $field->decision;
                    $kind = $field->field_key === null ? 'unknown' : (string) $field->field_key;
                    $script = $field->script_class === null ? null : (string) $field->script_class;
                    $group = $field->publisher_group === null ? null : (string) $field->publisher_group;
                    $stratum = self::stratumOf($group, $script, $kind);

                    // Carried whole, with no decision fields and no stratum: it
                    // is the court's sentence, not a value with a guarantee
                    // pending. Counted apart from the values for the same
                    // reason — folded into the candidate total it would read as
                    // 3,600 more values awaiting certification.
                    if ($kind === self::CITED_CONTEXT_KIND) {
                        $context[] = [
                            'kind' => $kind,
                            'text' => $field->extracted_value,
                            'page_number' => $field->evidence_page_number,
                            'script_class' => $field->script_class,
                        ];

                        continue;
                    }

                    $isCertified = self::isCertified($decision);
                    $bucket = $isCertified ? 'certified' : 'candidate';

                    $kinds[$kind] ??= ['certified' => 0, 'candidate' => 0];
                    $kinds[$kind][$bucket]++;
                    $strata[$stratum] ??= ['certified' => 0, 'candidate' => 0];
                    $strata[$stratum][$bucket]++;

                    $value = [
                        'kind' => $kind,
                        'value' => $field->extracted_value,
                        'normalized_value' => $field->normalized_value,
                        'page_number' => $field->evidence_page_number,
                        'certified' => $isCertified,
                        'decision' => $decision,
                        // The level the claim was certified at, and the basis it
                        // rests on. ADR-018 requires both to travel with the
                        // value: α = 0.10 is not α = 0.05, and a threshold
                        // borrowed from a wider pool — a `decision_basis` of
                        // anything but `stratum` — is valid for that pool and
                        // is not a conditional statement about this stratum.
                        // 203 accepted court values are borrowed today.
                        'decision_alpha' => $field->decision_alpha,
                        'decision_basis' => $field->decision_basis,
                        'script_class' => $field->script_class,
                        'stratum' => $stratum,
                        'nonconformity_score' => $field->nonconformity_score,
                        'score_basis' => $field->score_basis,
                    ];

                    if ($isCertified) {
                        $certified[] = $value;

                        continue;
                    }

                    $candidates[] = $value;
                }
            });

        return [
            'certified' => $certified,
            'candidates' => $candidates,
            'context' => $context,
            'kinds' => $kinds,
            'strata' => $strata,
        ];
    }

    /**
     * @param  array<int, SourceEndpoint>  $endpoints
     * @return array<string, mixed>
     */
    private function manifest(
        SourcePublisher $publisher,
        array $endpoints,
        int $holdoutPercentage,
        int $limit,
        CourtCorpusTally $tally,
    ): array {
        $snapshot = PublicationSnapshot::current();

        return [
            'generated_at' => now()->toIso8601String(),
            'publisher' => $publisher->slug,
            'field_kinds_exported' => self::FIELD_KINDS,
            'documents_limited_to' => $limit > 0 ? $limit : null,
            // Which snapshot the public site was serving when this ran.
            //
            // Not the source of the values: ADR-021 keeps the pipeline's tables
            // ("what do we believe now") apart from a snapshot ("what did you
            // publish, and on what basis"), and this export reads the former. A
            // consumer comparing a certified value here against the site can
            // only do so against the label named here, and even then the
            // pipeline may have moved since. Naming a snapshot without saying
            // which way round would be the overclaim.
            'published_snapshot' => [
                'label' => $snapshot?->label,
                'published_at' => $this->iso($snapshot?->published_at),
                'figures_in_that_snapshot' => $snapshot?->figure_count,
                'read_from' => 'the live extraction tables, not the snapshot; the label records what was published '
                    .'when the export ran, so a certified value here can be matched against the site as it then stood.',
            ],
            'holdout' => [
                'percentage' => $holdoutPercentage,
                'rule' => 'the first eight hex digits of the document sha256, read as an integer, modulo 100, '
                    .'below the percentage',
                'reproducible_without_this_file' => true,
            ],
            'documents' => $tally->documentCounts(),
            'pages' => $tally->pageCounts(),
            'values' => $tally->valueCounts(),
            'field_kinds' => $tally->kindCounts(),
            // Keyed publisher × script × kind, because that is the stratum a
            // guarantee is conditional on, and a count keyed any coarser way
            // reads as a wider claim than the one certified.
            'by_stratum' => $tally->strata(),
            // What these documents also hold and this export does not carry. A
            // consumer who needs the money figures should know they were left
            // out rather than conclude the judgments have none.
            'field_kinds_present_but_not_exported' => $this->excludedKinds($publisher),
            'sources' => $this->sources($publisher, $endpoints, $tally),
            'personal_names' => self::PERSONAL_NAMES_NOTE,
            'certification' => 'A value under `certified_values` carries a split-conformal guarantee on the '
                .'reading: the level is `decision_alpha`, and the population it is conditional on is `stratum` '
                .'unless `decision_basis` is not `stratum`, in which case the threshold was borrowed from a wider '
                .'pool and is not a conditional claim about this stratum. A value under `candidate_values` carries '
                .'no guarantee of any kind. The guarantee is about the reading and never about the meaning: a '
                .'certified date is a string the system is confident it read correctly, not a date it vouches for.',
        ];
    }

    /**
     * Kinds this publisher's documents hold that the export leaves behind.
     *
     * Counted over the current runs, so the figure is comparable with the ones
     * above it rather than a row count over the whole field table.
     *
     * @return array<string, int>
     */
    private function excludedKinds(SourcePublisher $publisher): array
    {
        return ExtractionField::query()
            ->where('publisher_group', $publisher->slug)
            ->whereIn('extraction_run_id', ExtractionRun::currentIds())
            // Cited context is exported, under its own key, so it is not one
            // of the kinds left behind. Listing it here said the manifest held
            // none of something it held 3,116 of.
            ->whereNotIn('field_key', [...self::FIELD_KINDS, self::CITED_CONTEXT_KIND])
            ->selectRaw('field_key, count(*) as aggregate')
            ->groupBy('field_key')
            ->pluck('aggregate', 'field_key')
            ->map(fn (int|string $count): int => (int) $count)
            ->all();
    }

    /**
     * The licence and attribution recorded for every endpoint this export drew from.
     *
     * The authorising sentence is on the publisher, in metadata, because
     * `access_decision` is a 64-character label and a truncated authorisation
     * is worse than none. Both are carried: a redistributed corpus whose terms
     * have to be looked up elsewhere is a corpus whose terms will not be looked
     * up.
     *
     * @param  array<int, SourceEndpoint>  $endpoints
     * @return list<array<string, mixed>>
     */
    private function sources(SourcePublisher $publisher, array $endpoints, CourtCorpusTally $tally): array
    {
        $metadata = $publisher->metadata;
        $documents = $tally->documentsByEndpoint();

        $sources = [];

        foreach ($endpoints as $endpoint) {
            $name = (string) $endpoint->name;

            $sources[] = [
                'endpoint' => $name,
                'base_url' => $endpoint->base_url,
                'publisher' => $publisher->slug,
                'publisher_name' => $publisher->name,
                'canonical_url' => $publisher->canonical_url,
                'attribution_name' => $publisher->attribution_name,
                'rights_decision' => $publisher->rights_decision,
                'authorisation' => $this->recorded($metadata, 'authorisation'),
                'authorisation_recorded_at' => $this->recorded($metadata, 'authorisation_recorded_at'),
                'published_terms' => $this->recorded($metadata, 'published_terms'),
                'access_decision' => $endpoint->access_decision,
                'robots_override_reason' => $endpoint->robots_override_reason,
                'plaintext_override_reason' => $endpoint->plaintext_override_reason,
                // Zeroes stay. An endpoint that contributed nothing is a
                // recorded governance decision, and dropping it here would make
                // the export look as though it drew on everything the publisher
                // offers.
                'documents_exported' => $documents[$name] ?? 0,
            ];
        }

        return $sources;
    }

    /**
     * A timestamp as an ISO string, whatever shape the attribute arrives in.
     *
     * `retrieved_at` and `published_at` are cast to date objects on their
     * models, but `app/Models` is outside the paths PHPStan analyses, so from
     * here they are untyped — and an attribute read straight off a query
     * builder rather than through a cast really is a string. Calling a date
     * method on it would be fatal, and the export runs for minutes before
     * reaching this line.
     */
    private function iso(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->toIso8601String();
        }

        // Already a string from the database: passed through as read rather
        // than reformatted, because guessing at a format is how a timestamp
        // silently shifts by a timezone.
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * One recorded fact out of a publisher's metadata, or null if it is absent.
     *
     * The column is cast to an array, and a publisher registered before a key
     * existed simply does not have it. Reaching into it directly asserts a
     * shape the column does not guarantee.
     */
    private function recorded(mixed $metadata, string $key): mixed
    {
        return is_array($metadata) && array_key_exists($key, $metadata)
            ? $metadata[$key]
            : null;
    }
}
