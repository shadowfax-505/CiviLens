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
before concluding table detection is broken.
