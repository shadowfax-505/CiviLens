# Runbook

What to run, in what order, and what each answer means. Every command here
exists; a test fails if one is added and not written down, because a runbook
that drifts is worse than none — an operator trusts it and it is wrong.

Nothing here needs arguments unless it says so. Anything that changes stored
data reports first and acts only with `--apply`.

## What has to be installed

Every external tool here is optional at runtime and fails closed, so a missing
one narrows what the corpus contains rather than breaking anything visibly.

| tool | without it |
| --- | --- |
| `pdftoppm`, `pdftotext` (poppler) | nothing is read at all |
| `tesseract` with `ben+eng` | scanned pages abstain |
| PaddleOCR sidecar | no page gains a table |
| `soffice` (LibreOffice) | word-processor documents are acquired and never read |

Set `EXTRACTION_SOFFICE_BINARY` if `soffice` is not on `PATH`. LibreOffice
renders those documents to PDF so the rest of the pipeline can lay eyes on
their pages — and because the pagination a reader then sees is ours rather than
the publisher's, the evidence page says so for exactly those documents. See
ADR-022.

## Is it working?

```bash
php artisan civiclens:health
```

Exits non-zero when something is broken. Everything in this pipeline is
optional at runtime and fails closed, which is right and also silent: table
detection was dead for days because its Python environment sat under `/tmp` and
the operating system removed it, and pages simply stopped gaining tables. That
looks exactly like a corpus without any.

```bash
php artisan civiclens:sources-report
```

What every registered endpoint produced, and why it produced nothing. Exits
non-zero when a source yielded nothing and recorded no failure, because a zero
next to a stated reason is a governance decision and a zero next to silence is
a breakage.

## Acquiring

```bash
php artisan civiclens:sources-dispatch [--endpoint=13] [--force]
php artisan civiclens:harvest-links [--apply]      # documents linked from pages already archived
php artisan civiclens:verify-sources <url>          # before registering anything new
php artisan civiclens:register-bangladesh-sources
php artisan civiclens:build-ca-bundle               # publishers that omit their intermediates
```

`harvest-links` reads bytes already fetched and proposes what they point at; it
never fetches. Every proposal passes the endpoint's own allowlist, and
acquisition remains the only thing that reaches the network.

## Extracting

```bash
php artisan civiclens:reroute-pages [--apply]       # pages whose stored text the router would now reject
php artisan civiclens:extract-tables [--all] [--again]
php artisan civiclens:generate-review-candidates [--from-cells]
php artisan civiclens:score-fields [--sync] [--labelled]
php artisan civiclens:classify-abstentions
php artisan civiclens:backfill-word-geometry
php artisan civiclens:prune-candidates [--apply]
```

Order matters: candidates are generated from page text and table cells, scored
by a second read of the page, and only then decided.

`score-fields` visits pages holding an unscored value first, then pages scored
by an earlier reader, then the rest in id order. Before that it ordered by id
alone, so a page read today sat behind every page ever read and no limit short
of the whole corpus reached it.

`civiclens:health` reports which readers produced the scores on record. More
than one version means a re-score is owed — a score from an older locator
defers rather than over-accepts, so nothing breaks and nothing complains, and
the guarantee quietly covers less of the corpus than it appears to.
Recalibration follows the re-score (ADR-019).

`--sync` scores in the calling process. Scoring shares one queue with the
crawlers, and a publisher rate-limited to a request a minute holds it for hours.

Table detection is the slowest thing here — about 75 seconds a page, with a
tail of ten to sixteen minutes — so it records that it ran and never repeats a
page. Use `--again` only after a page has been re-read and its word geometry
has changed underneath the old grid.

## Certifying

```bash
php artisan civiclens:score-discrimination     # is the score worth thresholding at all
php artisan civiclens:calibration-report       # thresholds, realized risk, and the interval around it
php artisan civiclens:decide-fields            # apply the thresholds
```

**Re-score and recalibrate after any change to extraction.** A score computed
against characters that have since changed is a measurement of the wrong thing,
and the decisions rest on it.

## Publishing

```bash
php artisan civiclens:publish "2026-08-26-a" --notes="..." [--publish]
```

Building and publishing are separate. A snapshot copies the values it
publishes, so a citation stays resolvable after the pipeline moves on, and a new
snapshot supersedes the old without deleting it. `civiclens:health` reports
drift when what is published falls behind what is certified.

## Answering people

```bash
php artisan civiclens:corrections                                    # what is waiting
php artisan civiclens:corrections --decide=<ref> --as=upheld|refused --because="..."
```

Exits non-zero while requests are waiting. A decision cannot be recorded
without a reason, and refusals are kept as fully as grants.

## Reporting

```bash
php artisan civiclens:paper-report        # every number a write-up needs
php artisan civiclens:limitations         # what the method cannot do, with the measurements
php artisan civiclens:reviewer-agreement  # whether two people judge alike
php artisan civiclens:screen-corpus       # names in the corpus against the published lists
php artisan civiclens:extraction-summary
php artisan civiclens:publisher-report <slug> [--save]
php artisan civiclens:screen <name>
php artisan civiclens:normalise-notice
php artisan civiclens:load-screening-entities
php artisan civiclens:probe-legibility
php artisan civiclens:evaluate-benchmark
php artisan civiclens:integrity-run
```

## Two things that have bitten

**Do not delete queued jobs by hand.** `ExtractPageTables` is unique for two
hours, and deleting its rows leaves the locks behind: every re-dispatch is then
dropped silently while the command reports success. Clear
`cache_locks` alongside the jobs, or wait it out.

**Do not run two workers against SQLite.** They contend, and the failures look
like unrelated bugs. One worker, or move to PostgreSQL.

**The queue is one line, in order.** Table detection queued behind a backlog of
acquisitions does not start until that backlog clears, and a table count that
has not moved in half an hour usually means the worker is still doing something
else rather than that anything is wrong. Check what is at the head of `jobs`
before concluding table detection is broken. One slow scanned document can hold
the line for a quarter of an hour on its own.

To run table detection ahead of the crawl, give those jobs their own queue name
and let the worker prefer it:

```bash
php artisan civiclens:extract-tables --all
php artisan queue:work --queue=tables,ingestion --max-time=50400 --tries=1 --timeout=900
```

Move already-queued jobs by updating their `queue` column — never by deleting
and re-dispatching them, for the unique-lock reason above.

**Not every page is worth the same two minutes.** Detection runs about two
minutes a page, so the whole corpus is days of continuous work, and roughly
two thirds of it confirms a page has no table. What a table buys is a value
that knows its row and column: cell-derived values certify at 88% against 77%
and never fail to locate. So a page holding a value that could not be located
gains something immediately, a page holding candidates gains something later,
and a page holding neither gains nothing until it does.

Split the queue by that, and let the worker prefer in order:

```bash
php artisan queue:work --queue=tables,tables-candidates,tables-later,ingestion --max-time=50400 --tries=1 --timeout=900
```

The page id is in the job payload (`pageId";i:<n>`), which is what makes the
split possible without re-dispatching anything.

**Where the two minutes actually go**, measured on this machine with the worker
paused, because three plausible ways to speed it up turned out to be worth
nothing and one of them would have lost data:

| step | cost |
| --- | --- |
| copying the artifact out of storage | 0.08s |
| `pdftoppm` for one page at 150 DPI | 0.31s |
| python import + building the pipeline | 7.3s |
| **inference** | **10–91s, averaging ~58s** |

So caching the materialised PDF across pages saves nothing, and batching many
pages into one python process — which does avoid reloading the models — saves
about 11%. The cost is the model, and it is paid per page.

A cheap pre-filter to skip pages with no table was tested against the 896
already-examined pages, where it is known which hold one. Word left-edges
snapped to a 12px grid, skipping pages with fewer than N aligned columns: at its
loosest setting it skips 35 pages, and **19 of those hold a table**. The signal
is not there. Do not add a skip heuristic without checking it against the
examined pages first — a false negative loses a table silently, and cell-derived
values are the ones that certify best.

**The one lever that works is not competing.** Inference is CPU-bound; running
the test suite alongside it roughly doubles the wall time per page. A page timed
at 237s with the worker running measured 65s with it paused.

**`retry_after` must exceed the longest job.** It is 90 seconds by default and
table detection runs about ninety seconds a page with a tail past ten minutes,
so the queue would hand a still-running job to another worker and the same page
would be read twice. `DB_QUEUE_RETRY_AFTER` is set to 1200 against the worker's
`--timeout=900`.
