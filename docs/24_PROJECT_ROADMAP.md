# 24 Project Roadmap

Written 2026-08-19. Every figure here regenerates from `civiclens:paper-report`
and `civiclens:health`; where one is quoted it was read from those commands on
that date, and it will be stale by the time the corpus grows.

**Decisions taken:** build both the method and the product; wire the pipeline
into the public service; deploy to a public always-on server; open-ended
timeline; crawling does not defer to robots directives, on recorded legal advice
for a public-benefit research project.

## Part 0 — The constraint that shapes the sequence

A public transparency site fed by *certified* values, with 267 adjudications from
a single publisher, can publish only that publisher's values as certified.

| publisher | documents | labels | publishable as certified? |
| --- | --- | --- | --- |
| dgcivil-bangladesh | 9 | 267 | **yes** — 3 groups at α = 0.05, all conditional |
| cbad-bangladesh | 1 | 0 | no — deferred by rule |
| imed-bangladesh | 1 | 0 | no |
| bgpress gazette, parliament | 0 | 0 | no |

This is not a defect to engineer around. The bound means something only because
uncertified values are withheld, and withholding is what makes the certified ones
worth reading. Labels are the binding constraint on **certification only** — they
constrain nothing else on this roadmap.

The single highest-value hour available to this project is roughly 27 cbad
adjudications, which would produce a second certified publisher. A label produced
by the system would be the system marking its own homework (ADR-016), so there is
no substitute for a person at the review screen.

---

## Part I — What exists

### Acquisition
13 publishers and endpoints across six connector types — static HTML, direct
download, paginated API, e-GP listing, sitemap, and a Playwright browser renderer
for publishers whose lists are built by JavaScript. **3,693 resources discovered,
509 archived**: documents and web-page/record captures counted apart, because
conflating them once reported 11 documents as 413.

Fail-closed egress throughout: host allowlist, path prefixes, DNS pinning, byte
ceilings, malware scanning, quarantine — and the same guards rebuilt *inside* the
browser renderer, which blocked 11 off-allowlist requests on the first page it
rendered. Exponential backoff and auto-pause after repeated failure. TLS chain
repair for three government hosts that serve only a leaf certificate, their
intermediates fetched from the certificate's own AIA pointer; verification was
never disabled.

Robots is one project setting (`civiclens.ingestion.honour_robots`, off), not a
per-endpoint negotiation. When it is off the file is not fetched at all. Rate
limits are per endpoint and enforced either way.

Endpoints may carry a credential without storing one: the row holds a header name
and the *name* of a config key, and the value is read at request time and withheld
from any host outside the allowlist.

### Extraction
**1,799 pages**; born-digital text where it exists, OCR (`ben+eng`) where it does
not, and roughly a fifth abstained — attempted and declined, counted rather than
hidden. Legacy-font mojibake detected and routed to OCR; 92% of one publisher's
"native" pages were unreadable text layers. Word geometry persisted wherever
recoverable, which is what lets a value be marked on its page. Table structure
from a PaddleOCR sidecar that supplies **structure only** — text always comes from
Tesseract, because adopting a recognizer with no established Bengali quality would
silently change every page.

### Certification
Split conformal risk control per publisher × script, bounding
`P(auto-accepted AND wrong) ≤ α`. Three groups certified at α = 0.05,
conditionally, from 267 adjudications. Adaptive α: a group too small for 0.05 is
certified at the tightest level its own labels support (`α ≥ 1/(n+1)`) rather than
dismissed, with a fallback ladder and `basis` recorded so a borrowed threshold is
never reported as a conditional one (ADR-018).

The score was rebuilt and it mattered. Page-level OCR confidence ranked errors at
**AUC 0.5106** — a coin flip, identical for every field on a page. A second read
of each value's own marked region scores **AUC 0.8818** over 247 of 265
adjudicated fields (ADR-019).

A locator defect was withholding the sums of money: these documents print an
amount as `৩,৯৮,৭৪৮/-` and an exact match failed on the terminator, so the
figures that mattered went unlocated and deferred by rule while contents-page
section numbers were certified. Fixing it raised coverage from 205 to 247 of 265
and moved all three groups onto conditional certification at α = 0.05, where two
of them previously needed the ADR-018 ladder.

**6,547 accepted, 1,797 deferred.** The bound is applied, not merely computed.

### Publication
`civiclens:publish` freezes a snapshot of what the project is prepared to stand
behind, and `/public/evidence` serves it. A snapshot copies the values it
publishes rather than pointing at rows the pipeline will rewrite, so a citation
stays resolvable; building and publishing are separate acts; and a new snapshot
supersedes the old without deleting it (ADR-021).

### Review, screening, discipline
The review screen asks one question — *do these characters match the page?* — and
shows the value marked on the scanned page plus a close-up. Sampling is uniform at
random, never by confidence; "can't tell" is a first-class answer.

1,401 debarment entities including 28 Bangladesh-linked, name normalisation that
resolves `M/S SRS DESIGN AND FASHION LIMITED` to `SRS DESIGN & FASHION LTD.`, and
a screener that returns **candidates, never verdicts**.

569 tests, PHPStan level 8 strict, Pint, Rector, `composer validate --strict` on
every change; 22 ADRs; method and dataset cards free of hardcoded numbers; and
`civiclens:health`, added after table detection died silently for days because its
Python environment sat in `/tmp` and the OS purged it.

---

## Part II — What is not there yet

- **The product layer is demonstration rows**: 6 projects, 4 agencies, 0
  contracts. `/public/evidence` is real; `/public/projects` and the rest are not.
- **The corpus is effectively one publisher**, 9 documents of which certified
  anything.
- **It runs on one laptop** under launchd, against SQLite, with a 1.2GB Python
  environment inside the repo's storage directory.
- **Backlog**: 143 failed jobs and roughly 1,700 unscored candidates at the time
  of writing, visible because health reports them.
- **The amount pattern over-matches.** 1,484 of the 6,547 accepted values (22.7%)
  are section numbers like `২.২` off contents pages. They were read correctly and
  the bound holds as stated; the label is what overreaches (ADR-021).
- **1,012 values still cannot be located** and defer by rule. Improving location
  coverage certifies more than tuning the score would.

---

## Part III — Constraints

**Bangladeshi audit documents are genuinely scarce on the open web.** Ten
directorate domains no longer resolve; CAG publishes no index of its own storage;
e-GP's detail servlet answers POST only. The corpus is small because the sources
are, not because the crawler is weak. **An email to CAG or IMED requesting their
archive is likelier to yield documents than any amount of engineering.**

**Publishers are fragile and hostile by accident**: JavaScript-built lists,
incomplete TLS chains, POST-only servlets, blocks after sustained crawling.

**Licence entanglement.** OpenSanctions data is CC BY-NC 4.0 — attribution
required, commercial reuse forbidden — and it propagates to anything derived from
it. Wikidata is CC0 and carries nothing.

**Legal and ethical exposure.** Crawling does not defer to robots directives, on
recorded legal advice. Publishing name matches risks asserting identifications the
data does not support. A public site publishing government financial data invites
correction demands and takedown requests, which M12 must answer for.

**Silent degradation is the systemic risk.** Almost everything is optional at
runtime and fails closed, which is right and quiet. Health checks exist because
that combination cost days.

**One machine, one person.** SQLite in WAL mode is adequate at this size and is
not a public-service database. Two queue workers against it produce lock
contention immediately.

---

## Part IV — Modules

Each module: state → target → work → gate.

### M1 · Acquisition hardening
**State:** working; 143 failed jobs, most publisher-side.
**Target:** every failure explained or retired; no source silently empty.
**Work:** finish triaging the backlog into publisher-side (404, 500, blocked)
versus ours; retire dead endpoints with recorded reasons as CAG was; a
`civiclens:sources-report` naming every endpoint's last success and yield.
**Gate:** every registered endpoint has either documents or a recorded reason.

### M2 · Corpus expansion
**State:** 13 endpoints; open-web supply near exhausted.
**Target:** more documents per publisher, and at least one more publisher with
real documents.
**Work:** follow cbad's links into `cag.org.bd/storage` (the index CAG lacks);
revisit `fapad`; a bounded depth-1 follow for listing pages that link further
listings; the OpenSanctions hosted API alongside the bulk file. **Ask the
publishers directly** — see the constraint above.
**Gate:** documents by publisher grows; each new one extracts to real text.

### M3 · Extraction completeness
**State:** roughly a fifth abstained; scoring backlog outstanding.
**Target:** backlog cleared; abstention understood rather than merely counted.
**Work:** drain table and scoring queues; sample abstained pages and classify why
(blank, poorly scanned, unusual layout); consider a third OCR attempt at higher
DPI for the near-threshold band; extend word geometry to remaining native pages.
**Tighten the amount pattern** so contents-page section numbers stop being
generated as amount candidates — this is candidate generation, not certification,
and re-scoring plus recalibration must follow it (ADR-019).
**Gate:** health reports zero backlog; abstention has a breakdown, not just a rate.

### M4 · Review throughput
**State:** works; 267 labels; one reviewer.
**Target:** more labels per hour, and the option of more than one reviewer.
**Work:** keyboard-only flow with prefetching; a session counter showing progress
toward each group's threshold; **inter-rater support** — the same item shown to
two reviewers with agreement measured, which is a publishable result in itself;
a short reviewer guide covering the digit/script and separator rules.
**Gate:** a measured labels-per-hour figure, and agreement on a double-judged
subset.

### M5 · Certification depth
**State:** three groups at α = 0.05; 10 observed errors.
**Target:** claims that survive review.
**Work:** Clopper–Pearson intervals on realized risk so the coarseness of 10
errors is explicit; sensitivity analysis across α; a leakage check that stays
meaningful as the score improves; clustered conformal if publishers multiply.
**Gate:** every reported guarantee carries its uncertainty.

### M6 · Screening and entity resolution
**State:** normaliser, screener, 1,401 entities; no Bangladeshi names to screen.
**Target:** names to screen, screened responsibly.
**Work:** organisation-name extraction from audit text as a fourth field kind
(`amount`, `date`, `reference`, `organisation`), reusing the existing review and
certification pipeline; e-GP detail pages via the browser renderer's POST at one
request a minute, resumable, for award-level supplier names; auto-link exact
matches with the claim stated as *"this name matches an entry on a published
list"*; fuzzy matches to review.

**Company registry, without OpenCorporates.** Their API is unavailable. The right
answer is the primary source rather than an aggregator: **RJSC** —
`app.roc.gov.bd` is reachable and verifies once its intermediate is in the CA
bundle, and it is the authoritative Bangladeshi registrar. It is a search portal
rather than a bulk download, so it is a lookup connector: resolve a name, record
what the registrar returned, cache it. GLEIF and Wikidata cover the larger firms.
**Gate:** a screened name resolves to a real entity, and no output asserts an
identification.

### M7 · Publication ✅ delivered
**Delivered 2026-08-19 as a snapshot layer, not as domain records.** The original
target — project certified values into `tenders`, `contracts` and the rest — is
not available and should not be attempted: `tenders` requires a project, a budget,
an agency, a method, a category, a status and a title, while an e-GP listing row
carries a reference and a nature and an audit page carries a figure. Filling those
columns means inventing them.

So publication takes the shape the evidence supports: documents, the certified
figures read off their pages, and the notices a publisher listed, each carrying
provenance, the α it was certified at and the basis of that certification. See
ADR-021.
**Remaining:** an admin screen for building and publishing a snapshot, rather than
a command; and a scheduled staleness check, since nothing rebuilds a snapshot
automatically and a stale one is a real failure mode.

### M8 · Public portal and provenance
**State:** `/public/evidence` serves the snapshot; the rest of the portal serves
demonstration rows.
**Target:** a site where every figure shows where it came from and how sure it is,
and no page shows a seeded row.
**Work:** a provenance panel showing the marked region of the page image, not only
the page number; explicit "not verified" presentation for deferred values, since
most of the corpus reads that way; a methods page in plain language; retire or
clearly mark the demonstration pages; search and filters over published records.
**Gate:** a member of the public can trace any published figure to a scanned page.

### M9 · Search and discovery
**State:** database-backed search over demonstration data.
**Target:** search over published records and document text, Bengali included.
**Work:** index published figures and page text; Bengali tokenisation and
normalisation, including legacy-font transliteration so a query in Bengali digits
finds a page storing Latin ones; result provenance and status inline.
**Gate:** a Bengali query returns the right page from a scanned document.

### M10 · Analytics and intelligence
**State:** services exist over demonstration data.
**Target:** counts and comparisons over certified figures, never accusations.
**Work:** aggregate only certified values with the deferred share always shown;
publisher-level indicators (amendments, revisions) as counts; remove or gate any
inference the data does not support; keep the existing wording guardrails.
**Gate:** no screen states a conclusion the underlying data cannot support.

### M11 · Deployment and operations ★
**State:** one laptop, launchd, SQLite.
**Target:** a public always-on service.
**Work:** provision a server; migrate SQLite → PostgreSQL (the schema is portable,
and the WAL and busy-timeout work becomes moot); real queue workers and scheduler;
artifact storage on disk or S3-compatible object storage with backups; the Python
and Playwright environments provisioned by script, never in `/tmp`; secrets from
the environment; TLS, domain, and a status page; `civiclens:health` wired to
alerting rather than a daily log line.
**Gate:** the service survives a reboot and a deploy without a human, and health
failures reach a person.
**Depends on:** M8 for something worth hosting.

### M12 · Security, privacy and moderation
**State:** strong ingestion security; nothing public-facing hardened.
**Target:** safe to expose.
**Work:** review authentication, roles and rate limits for a public deployment; a
correction and takedown route with a recorded decision trail; personal-name
handling in audit documents (they contain individuals' names — the debarment list
alone holds several); an abuse and load plan.
**Gate:** an external security review passes, and a correction request has a
documented path.

### M13 · Research artifacts and publication
**State:** `civiclens:paper-report`, method card, dataset card, 22 ADRs.
**Target:** a submittable paper and a reproducible archive.
**Work:** figures and tables generated from the report rather than transcribed;
methods drawn from the method card; a limitations section stating single-publisher
dependence, the second read's correlated-failure blind spot, the unlocatable
share, and the amount pattern's over-matching; a Zenodo deposit with DOI; **a
LICENSE file** — the repo is public with none, which means all rights reserved and
contradicts the open-licence position.
**Gate:** a clean checkout regenerates every number in the paper.

### M14 · Governance and documentation
**State:** 22 ADRs, cards, standing rules.
**Target:** decisions remain findable and current.
**Work:** keep the dataset card accurate as sources change; a runbook for the
operator; consolidate the 30 numbered docs, several of which predate the pipeline
and describe intentions rather than the system as built.
**Gate:** documentation describes the system that exists.

---

## Part V — Sequence

**Now, needing no reviewer:** finish the backlog (M1, M3) · tighten the amount
pattern and recalibrate (M3) · RJSC lookup connector (M6) · corpus growth,
including asking publishers directly (M2).

**At the review screen:** ~27 cbad adjudications → a second certified publisher.

**Then:** M8 portal on real data → M11 deployment → M12 security review → public
launch. M2 and M6 continue alongside. M13 last, once the numbers stop moving.

---

## Verification

```bash
php artisan test && vendor/bin/pint --test && vendor/bin/phpstan analyse && vendor/bin/rector process --dry-run && composer validate --strict
```

Per module: `civiclens:health` green with no backlog; `civiclens:paper-report`
showing documents by publisher, certified groups and score AUC; and for M7/M8, a
published figure traced by hand back to its page image.

## Standing rules

Counts, never conclusions. Structure is context, not certification. A match is a
candidate, not an identification. Uncertified means withheld or plainly marked,
never quietly published. Every defect fix proven by a test that fails on pre-fix
code. Additive reversible migrations only. Look at the actual data before claiming
what it contains.
