<?php

namespace App\Services\Corpus;

/**
 * The manifest's counts, accumulated as documents are written and never held.
 *
 * A manifest could be built by reading the finished JSONL back, and that is the
 * one thing this class exists to avoid: 55 MB of records re-parsed to count
 * them, on a pipeline that has run out of memory four times by holding a
 * collection whole. Every figure here is a scalar or a small map, so the export
 * costs the same memory at 2,507 documents as at ten.
 *
 * Counts are kept split by split as well as in total, because "counts per
 * split" is the figure that says whether a holdout is the size it claims to be.
 */
class CourtCorpusTally
{
    /** @var array<string, int> */
    private array $documents = [CourtCorpusExport::TRAIN => 0, CourtCorpusExport::HOLDOUT => 0];

    /** @var array<string, int> */
    private array $pages = [CourtCorpusExport::TRAIN => 0, CourtCorpusExport::HOLDOUT => 0];

    /** @var array<string, array{certified: int, candidate: int}> */
    private array $values = [
        CourtCorpusExport::TRAIN => ['certified' => 0, 'candidate' => 0],
        CourtCorpusExport::HOLDOUT => ['certified' => 0, 'candidate' => 0],
    ];

    /** @var array<string, int> */
    private array $pagesByScript = [];

    /** @var array<string, array{certified: int, candidate: int}> */
    private array $kinds = [];

    /** @var array<string, array{certified: int, candidate: int}> */
    private array $strata = [];

    /** @var array<string, int> */
    private array $byEndpoint = [];

    /**
     * Documents whose file is held but which nothing has read.
     *
     * A count rather than a silence. ADR-020 settled that an artifact producing
     * nothing is reported as a zero beside its reason, because dropping it
     * would make the corpus look as though it certifies everything it holds.
     */
    private int $withoutARun = 0;

    /**
     * @param  array{text: string, pages: list<array<string, mixed>>, scripts: array<string, int>}  $pages
     * @param  array{
     *     certified: list<array<string, mixed>>,
     *     candidates: list<array<string, mixed>>,
     *     kinds: array<string, array{certified: int, candidate: int}>,
     *     strata: array<string, array{certified: int, candidate: int}>,
     * }  $values
     */
    public function document(string $split, ?string $endpoint, array $pages, array $values): void
    {
        $this->documents[$split] = ($this->documents[$split] ?? 0) + 1;
        $this->pages[$split] = ($this->pages[$split] ?? 0) + count($pages['pages']);

        $this->values[$split] ??= ['certified' => 0, 'candidate' => 0];
        $this->values[$split]['certified'] += count($values['certified']);
        $this->values[$split]['candidate'] += count($values['candidates']);

        foreach ($pages['scripts'] as $script => $count) {
            $this->pagesByScript[$script] = ($this->pagesByScript[$script] ?? 0) + $count;
        }

        $this->kinds = $this->merge($this->kinds, $values['kinds']);
        $this->strata = $this->merge($this->strata, $values['strata']);

        $name = $endpoint ?? 'unattributed';
        $this->byEndpoint[$name] = ($this->byEndpoint[$name] ?? 0) + 1;
    }

    public function documentWithoutARun(): void
    {
        $this->withoutARun++;
    }

    /**
     * @return array<string, int>
     */
    public function documentCounts(): array
    {
        return [
            CourtCorpusExport::TRAIN => $this->documents[CourtCorpusExport::TRAIN] ?? 0,
            CourtCorpusExport::HOLDOUT => $this->documents[CourtCorpusExport::HOLDOUT] ?? 0,
            'total' => array_sum($this->documents),
            'held_but_never_read' => $this->withoutARun,
        ];
    }

    /**
     * @return array<string, int|array<string, int>>
     */
    public function pageCounts(): array
    {
        return [
            CourtCorpusExport::TRAIN => $this->pages[CourtCorpusExport::TRAIN] ?? 0,
            CourtCorpusExport::HOLDOUT => $this->pages[CourtCorpusExport::HOLDOUT] ?? 0,
            'total' => array_sum($this->pages),
            // Script, because it is half of the stratum a guarantee is
            // conditional on, and because ADR-020 records that these group
            // names are not reliably what they say: every accepted value in the
            // group named `en` sits on a page of Bengali set in a pre-Unicode
            // font. The count is what the classifier decided, not a claim about
            // the language on the page.
            'by_script' => $this->pagesByScript,
        ];
    }

    /**
     * @return array<string, int|array<string, int>>
     */
    public function valueCounts(): array
    {
        $certified = ($this->values[CourtCorpusExport::TRAIN]['certified'] ?? 0)
            + ($this->values[CourtCorpusExport::HOLDOUT]['certified'] ?? 0);
        $candidate = ($this->values[CourtCorpusExport::TRAIN]['candidate'] ?? 0)
            + ($this->values[CourtCorpusExport::HOLDOUT]['candidate'] ?? 0);

        return [
            'certified' => $certified,
            'candidate' => $candidate,
            'total' => $certified + $candidate,
            CourtCorpusExport::TRAIN => $this->values[CourtCorpusExport::TRAIN] ?? ['certified' => 0, 'candidate' => 0],
            CourtCorpusExport::HOLDOUT => $this->values[CourtCorpusExport::HOLDOUT] ?? ['certified' => 0, 'candidate' => 0],
        ];
    }

    /**
     * @return array<string, array{certified: int, candidate: int}>
     */
    public function kindCounts(): array
    {
        ksort($this->kinds);

        return $this->kinds;
    }

    /**
     * @return array<string, array{certified: int, candidate: int}>
     */
    public function strata(): array
    {
        ksort($this->strata);

        return $this->strata;
    }

    /**
     * @return array<string, int>
     */
    public function documentsByEndpoint(): array
    {
        return $this->byEndpoint;
    }

    /**
     * @param  array<string, array{certified: int, candidate: int}>  $into
     * @param  array<string, array{certified: int, candidate: int}>  $from
     * @return array<string, array{certified: int, candidate: int}>
     */
    private function merge(array $into, array $from): array
    {
        foreach ($from as $key => $counts) {
            $into[$key] ??= ['certified' => 0, 'candidate' => 0];
            $into[$key]['certified'] += $counts['certified'];
            $into[$key]['candidate'] += $counts['candidate'];
        }

        return $into;
    }
}
