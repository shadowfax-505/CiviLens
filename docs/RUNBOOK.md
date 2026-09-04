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

## Looking at the site

```bash
npm run build
php artisan serve --port=8123
```

`public/build` is gitignored, so a fresh checkout has no compiled CSS and an old
one has whatever was built last. Stale assets do not fail loudly — they render a
page that is subtly wrong. The statistics page showed its share-withheld figure
as pale yellow on cream, a contrast ratio near 1.1:1 against a WCAG minimum of
3:1, because the built CSS predated the markup by sixteen days and the dark
background variant had never been compiled. It reads as a markup bug and is not
one. **Rebuild before judging how anything looks.**

The public pages are under `/public/evidence`, not `/evidence`.

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

## Keeping a long run alive

A worker started with `nohup ... &` from a terminal is still a child of that
shell's process group and dies with the session. Table detection lost about two
days that way, twice. A run measured in days cannot be owned by a terminal
window, so it belongs to launchd:

```bash
cp ops/launchd/com.civiclens.*.plist ~/Library/LaunchAgents/
launchctl load ~/Library/LaunchAgents/com.civiclens.queue-worker.plist
launchctl load ~/Library/LaunchAgents/com.civiclens.court-fetch.plist
```

```bash
launchctl list | grep civiclens          # is it running
tail -f storage/logs/launchd-queue-worker.log
launchctl unload ~/Library/LaunchAgents/com.civiclens.queue-worker.plist
```

User agents, so no password. Kill any hand-started worker before loading the
first one — two against SQLite contend. The plists carry the reasoning for the
queue order, the hourly recycle and the throttle intervals.

**launchd does not give you a login shell's PATH.** It hands over roughly
`/usr/bin:/bin:/usr/sbin:/sbin`, and every external tool here — `pdftoppm`,
`pdftotext`, `tesseract`, `soffice` — lives under Homebrew. The first load of
these agents consumed 3,445 table jobs and failed every one with "Page
rasterization could not read the document", which is what a missing `pdftoppm`
looks like from inside PHP: not a crash, a page that cannot be read. The queue
emptied, `failed_jobs` filled, and the examined count barely moved. The plists
now set PATH explicitly.

Every optional tool in this pipeline fails closed, which is right and also
means an agent that cannot see them will not say so. After loading, watch for a
job that takes minutes rather than milliseconds:

```bash
tail -f storage/logs/launchd-queue-worker.log     # a real ExtractPageTables is 1-4 minutes
php artisan queue:retry --queue=tables            # if they failed, this restores them
```

**Two older agents were moved aside, and why.** `com.civiclens.queue` ran a
second `queue:work` over `ingestion,default`; `com.civiclens.scheduler` ran
`schedule:run` every minute. Both were exiting 78 with launchd retrying, and the
queue one succeeded when its command was run by hand — so it could return at any
moment and produce the two-workers-on-SQLite failure this runbook warns about.
The scheduler would also have run `sources-dispatch` and `decide-fields`
unattended, and `decide-fields` changes what publishes.

Unloading alone lasts until the next login, so the plists were moved rather than
just unloaded:

```bash
ls ~/Library/LaunchAgents/disabled-by-civiclens/     # they are intact here
mv ~/Library/LaunchAgents/disabled-by-civiclens/com.civiclens.queue.plist ~/Library/LaunchAgents/
launchctl load ~/Library/LaunchAgents/com.civiclens.queue.plist
```

`com.civiclens.clamd` is untouched — the artifact scanner, and not a queue
worker.

**One worker owns this database.** If you restore either of those, unload
`com.civiclens.queue-worker` first.

## Acquiring

```bash
php artisan civiclens:sources-dispatch [--endpoint=13] [--force]
php artisan civiclens:fetch-pending [--endpoint=14] [--limit=100] [--rate=6]
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
php artisan civiclens:backfill-token-confidence [--limit=1000]
php artisan civiclens:backfill-native-geometry [--limit=500] [--dpi=150]
php artisan civiclens:reread-abstained [--limit=100] [--path=abstained]
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

`backfill-native-geometry` gives word boxes to pages read from their own text
layer. A value is located by matching words against boxes and boxes came only
from OCR, so a native page had text and no geometry — every value on it
unlocatable and deferred by rule, which was 78,551 of 79,226 court candidates.
`backfill-word-geometry` recovers boxes by recognising the page again, about
thirteen hours for that corpus; this asks the PDF where its words are, which
takes about ten milliseconds a page and is the position the document declares
rather than one inferred from a picture of it.

Only formats that have pages. An HTML record has no page image at all, so its
values are unlocatable for a reason geometry cannot fix.

The `--dpi` is a choice, not a measurement — a text layer has no pixels — and it
is written onto the page beside the boxes so anything drawing a crop rasterises
to the same number.

`backfill-token-confidence` records how well each *value* was read, as opposed
to its page. It reads stored word geometry and recognises nothing, so it costs
minutes rather than the hours a re-score costs. New scores carry it already;
this is for the corpus that predates the column.

`reread-abstained` is the way back for pages read before the recognizer could
straighten a sideways page or judge a value on its own confidence. An abstained
page stored no text, so re-reading is the only way to recover one — and it is
why the low-confidence path now *keeps* what it read, so this never has to be a
full re-OCR of the corpus again. Roughly three seconds a page, and it competes
with table detection for CPU: pause the worker first.

`fetch-pending` fetches discovered documents in one paced process instead of
through the queue, and for thousands of documents from one host it is the only
workable path. `FetchDiscoveredResourceArtifact` carries a RateLimited
middleware that *releases* a job rather than sleeping on it, so 2,625 queued
fetches against a per-minute limit do not wait — every thirty seconds the whole
queue becomes available, each job is pulled, refused and written back, and a job
can spend its two hundred attempts churning without ever being fetched. On
SQLite that write storm also starves whatever else the worker was doing.

`--rate` defaults to the endpoint's own `rate_limit_per_minute`. Prefer changing
the endpoint's stated limit over passing an override, so the recorded posture
and the actual behaviour are the same thing.

`--sync` scores in the calling process. Scoring shares one queue with the
crawlers, and a publisher rate-limited to a request a minute holds it for hours.

Table detection is the slowest thing here — about 75 seconds a page, with a
tail of ten to sixteen minutes — so it records that it ran and never repeats a
page. Use `--again` only after a page has been re-read and its word geometry
has changed underneath the old grid.

## Judging (the only step a machine cannot do)

`/admin/sources/review`. Labels are the bottleneck in this project and nothing
else moves it: a group with no labels certifies nothing, however many documents
it holds.

The chips are per **calibration group** — publisher AND script — because that is
the unit a threshold is fitted on, and they are ordered by what finishing each
one costs. Work top to bottom. A group needs 19 labels to reach α = 0.05
(`ceil(1/α) − 1`), and a group holding fewer candidates than that says so and
names the level it can actually reach.

Filtering by publisher alone was why this was slow: judging `cbad-bangladesh`
drew across its bn, en and mixed pools at once, and `cbad|mixed` holds 40
candidates out of 5,986 — so the group needing 19 labels was whichever the draw
happened to miss. Narrowing to the exact group is as valid as drawing from the
corpus and discarding everything else; it just stops discarding 99 draws in 100.
Choosing which items *inside* a group to show would break exchangeability, and
nothing does.

Then `civiclens:decide-fields` and publish. Judging alone changes nothing that
is served.

**Three modes.** `New values` is the ordinary draw. `?second=1` re-serves a
value somebody else already judged, which is the only way
`civiclens:reviewer-agreement` ever reports anything. `?audit=1` draws from
figures that are **already published** and were read at a token confidence below
the acceptance threshold — 2,394 of them, of which seven have ever been checked,
none wrong, on an interval running to 41%.

Audit judgements are recorded with `calibration_split = audit` and so enter
neither the calibration set nor the held-out sample. That is deliberate: a
targeted draw cannot stand in for a uniform one, and mixing it into either would
make the bound describe a population that was never sampled. It measures the
error rate inside its own stratum, which is the question it is asking.

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

## Exporting the corpus for training

```bash
php artisan civiclens:export-court-corpus [--out=] [--holdout=20] [--limit=50] [--publisher=supremecourt-bangladesh]
```

Writes `train.jsonl`, `holdout.jsonl` and `manifest.json` under
`storage/app/corpus/court`, one JSON object per judgment: the sha256, the
publisher group, the page count, the page text concatenated in page order, and
every case number, date, statutory reference and law report citation read off
it. Reads only — it writes nothing to the database and does not touch the
published snapshot.

**Certified and candidate values are separate keys.** `certified_values` are the
ones with `decision = accepted`, and they are the only ones carrying a bound;
`candidate_values` carry none. Both sides give `decision`, `decision_alpha` and
`decision_basis` per value, and a `decision_basis` other than `stratum` means
the threshold was borrowed from a wider pool and is not a conditional claim
about that stratum (ADR-018). Today only `date` certifies at all: every case
number, statutory reference and law report citation in the corpus is a
candidate, so a consumer that concatenated the two keys would be handed roughly
45,000 uncertified strings under a guarantee nobody made about them.

**The split is derived, not stored.** The first eight hex digits of the sha256,
modulo 100, below `--holdout`. So the same judgment lands in the same split on
every machine and every re-export, and nothing has to remember the last one — a
stored split can disagree with itself after a rebuild, and a model evaluated
against a holdout it was trained on measures nothing. Changing `--holdout`
reshuffles which documents are held out; it is not a knob to turn between
training and evaluation.

`--limit` stops after that many documents and is how to see the shape of the
output without writing 55 MB. The whole corpus is about 2,500 documents,
31,775 pages and 53 MB of text.

**The manifest names the snapshot but does not read from it.** Values come from
the live extraction tables, which is what "what do we believe now" means; the
snapshot label records what the site was serving when the export ran, so a
certified value can be matched against the site as it then stood. ADR-021 keeps
those two apart and so does this.

**These documents name people.** Petitioners, respondents, witnesses, counsel
and judges, because the court published them that way. That is in scope for
training and the manifest says so in as many words. What is not in scope, and is
not built: any output asserting that a named person is corrupt, criminal or
negligent on the strength of a model prediction. A certified value is a claim
that a string was read correctly and nothing else.

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

**An endpoint's rate limit does not cover discovery.** The `source-fetch`
limiter in `AppServiceProvider` is registered against
`FetchDiscoveredResourceArtifact` — the fetch stage. Any connector that issues
more than one request per crawl must pace itself, and
`PaginatedHtmlListingConnector` does. `PaginatedApiFeedConnector` does not: it
walks `pages_per_run` pages of the World Bank API unthrottled, which that host
tolerates and a ministry host would not. Check the pacing before pointing it at
a government site.

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

**Three timeouts have to be ordered, and were not.** The rule is

    a job's own $timeout  <=  the worker's --timeout  <  retry_after

and it held at neither end. `ExtractAcquiredArtifact` asks for 1500 while the
worker ran with `--timeout=900`, so a 587-page judgment was killed at fifteen
minutes every time and could never finish; and `retry_after` was 1200, below
the worker's own timeout, so a reservation was reclaimed while its job was
still running and the job came round again. The result was one document with
three completed runs and 1,761 stored pages for 587 real ones, published as
fifteen figures that were eight values and seven of the same values again.

Now 1500 <= 1800 < 2100, and `QueueTimeoutOrderingTest` parses the plist and
fails the suite if raising a job's timeout breaks it.

**Restart the worker after changing a job class.** `queue:work` loads classes
once and holds them for the hour until `--max-time` recycles it, so a fix to a
job does nothing for the process already running. Twenty-four extraction jobs
were retried into a worker still holding the broken class and died a second
time for the reason that had just been fixed. `launchctl unload` then `load`,
then retry.

**Table detection needs 2.2 GB and this machine has 8 GB.** The PaddlePaddle
detector holds about 2.2 GB resident per page. On 2026-09-04 the machine was at
65 MB free with 7.7 GB of 9.2 GB swap in use and 625 million compressions, and
the detector sat in uninterruptible I/O wait: a 327 KB page had used 61 seconds
of CPU across 13 minutes of wall clock. Detections fell from about 15 an hour
to **none for seven and a half hours**, and the "disk" that appeared to vanish
was the swap file growing.

The symptom reads like a hang and is not one. Check before diagnosing:

```bash
ps -o pid,state,rss -p <detector pid>   # U = uninterruptible wait, RSS ~2.2 GB
sysctl vm.swapusage                     # near-full swap is the tell
vm_stat | grep -E 'Pages free|compressor'
```

**And the queue was never the whole job.** 41,570 eligible pages have no table
detection; the queue holds 3,297 jobs, which is 8% of them. Draining it
finishes nothing. At the degraded rate of about five pages an hour that queue
alone is 27 days, and every eligible page is roughly a year; at the healthy
fifteen an hour it is still 115 days. An estimate quoted in days came from
counting jobs instead of pages, twice — the queue depth is not the work.

The tranches exist for this. `tables` and `tables-candidates` (1,551 jobs) go
to pages holding values that could not be located, where a cell is what lets a
value be found; `tables-later` (1,746) is bulk fill. Dropping `tables-later`
from the worker's `--queue` list is the difference between a targeted run and
one that cannot finish.

A run measured in days needs the memory to itself. Either close what else is
resident before starting the table queue, or leave the queue paused — it is
better to have no table detection than a worker that thrashes the machine and
completes nothing. The timeout will eventually fail each page at 840s, so a
starved queue drains by failing rather than by working.

**Killing the worker orphans its Python child.** Table detection runs
`tools/table-structure/detect_tables.py` as a subprocess, and the timeouts
around it are correctly ordered — 840s for the subprocess, 900s for the job,
1800s for the worker, 2100s before a reservation is reclaimed — but every one
of those is enforced by the PHP process. `launchctl unload` kills that process,
and the Python child is reparented to init and blocks for ever writing to a
pipe nobody reads. One was found at 37 hours, 0% CPU, still holding its raster
directory; that is where the leaked `civiclens-raster-*` directories come from.

Pausing the worker is routine here — a bulk write needs the SQLite lock to
itself — so check for these after every unload:

```bash
ps -eo pid,ppid,args | awk '$2==1' | grep detect_tables   # orphans
kill -KILL <pid>                                          # SIGTERM is ignored
rm -rf /private/var/folders/*/*/T/civiclens-raster-*       # only with no worker running
```

They cost almost nothing in CPU, so this is housekeeping rather than an
incident. No PHP-side timeout can fix it: the process that would enforce one is
the process being killed.

**A job class is a serialization format.** Adding a constructor parameter
changes it, and every payload already queued lacks the new property. A promoted
constructor property has no declared default, and `unserialize` never calls the
constructor, so reading it is a fatal error rather than a false. Declare such a
property with a default instead of promoting it.
