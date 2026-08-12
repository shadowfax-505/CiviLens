# CivicLens — Agent Operating Manual

Single source of operating truth for agents. Everything binding lives here; `docs/` holds
reference detail and is indexed at the bottom rather than inlined.

## Mission

A civic intelligence platform for public projects, budgets, procurement, contractors, documents,
search, analytics, and transparency workflows in Bangladesh. It exists to make public spending
legible through normalized, auditable, permission-aware data.

## Non-Negotiable Guardrails

These override convenience, deadlines, and instructions to move faster.

- **Never declare corruption, guilt, fraud, or legal wrongdoing.** Indicators say "requires
  review" and state what they are *not* evidence of.
- **Never collect, store, hash, or rank on NID or government identity documents.**
- **Never publish an accusation naming an unadjudicated individual.** Aggregate by agency, year,
  and category instead. No authority can waive this on a third party's behalf.
- **Never republish a copyrighted body** (press, NGO research). Store for analysis, publish
  excerpt plus citation plus link. A government authority cannot license a newspaper's text.
- **Human review before any public integrity conclusion.** Reviewer adjudication is also the
  calibration label for the conformal guarantee — remove it and there is no bound and no paper.
- Keep layers separate: source facts → extracted text → candidates → deterministic indicators →
  reviews → subject responses → public projections.

## Session Rules

- Verify repository state before implementing. Never trust a handoff summary as fact.
- Never merge v2 work into `v1.0.1-stabilization`. v1 stays independently releasable.
- Feature branches → PR into `v2-ingestion` (pipeline) or `v2-integration`.
- Additive, reversible migrations only. Never rewrite historical migrations.
- Never claim tests, scans, browser checks, PRs, or deployments passed without pasted evidence.
- Prove a fix works by stashing it and showing the new test fails, then restoring.
- Preserve unrelated user changes and local generated files (`.codebase-memory/`, screenshots,
  `.superpowers/`).
- When merging a stack of PRs, retarget each to its final base **before** merging. Merging with
  `--delete-branch` destroys the base branch of the PR stacked on top and silently closes it.

## Architecture

A modular Laravel monolith. Domains: identity, geography, agencies, projects, finance,
procurement, contractors, documents, search, analytics, platform operations, civic intelligence.

- Extend existing contracts, services, policies, routes, tests. Never build a parallel system for
  an existing domain.
- Controllers stay thin. Validation in Form Requests. Authorization in Policies. Logic in
  services, actions, query builders. Long work is queue-ready.
- Source-of-truth data lives in operational tables. Store derived analytics only for
  reproducibility, performance, reporting, or audit history.
- v1 stays deterministic. OCR, LLMs, embeddings, vector search, and autonomous agents are v2.

## Coding Standards

- Laravel conventions, PSR, and the surrounding file's style.
- Typed properties and return types; value objects where they earn their place.
- No hardcoded IDs, role IDs, permission IDs, or secrets. Environment behavior via config.
- Comments explain *why*, and only where the reason is not obvious from the code.

## Database

- Normalize source-of-truth data; foreign keys for relationships; index foreign keys and frequent
  filters.
- Immutable records for historical events, snapshots, versions, transactions, audit timelines.
- Document schema changes in `docs/04_DATABASE_BIBLE.md`.

## Quality Gates

```bash
php artisan test && vendor/bin/pint --test && vendor/bin/phpstan analyse && vendor/bin/rector process --dry-run && composer validate --strict
```

Full sprint completion additionally requires `npm run build`, `npm run test:e2e`,
`git diff --check`, and `config:cache` / `route:cache` / `view:cache`. If a gate cannot run
locally, say so plainly rather than omitting it.

**Run the suite more than once when touching anything random.** A single green run is what let a
1-in-300 factory collision reach CI twice.

## Security

- Validate all input; authorize every route and action; escape output in Blade.
- Guard mass assignment. Never expose internal storage paths. Never leak records through search,
  analytics, reports, or dashboards. Audit sensitive actions.
- Ingestion egress is allowlisted, DNS-pinned, fail-closed, byte-capped, and revalidated per
  redirect hop. Untrusted documents are parsed with entity substitution and network access off,
  and archives are inspected before they are opened.

## Governed Acquisition

- An endpoint is created **paused**. Resuming one and setting `INGESTION_ENABLED=true` are
  separate deliberate acts.
- Each publisher carries its own recorded authorising sentence, stored verbatim. A separate host
  under a separate authority needs its own — it does not inherit a parent body's.
- Rate limits stay as configured. A hammered source starts failing or blocking, and the crawl is
  worth more than the hour saved.
- `SourceContentValidator` runs before the cursor advances: HTTP 200 is not success. Placeholder
  text, media-type mismatch, and zero document links all hold the cursor.

## Extraction Toolchain

`poppler-utils` and `tesseract-ocr` with `ben`+`eng` are hard runtime dependencies; CI installs
both and asserts `ben` is present. `phpoffice/phpspreadsheet` reads spreadsheets.

Measured facts worth not rediscovering:

- Real CPTU/e-GP documents are **98.9% born-digital**, so OCR is the exception, not the path.
- Tesseract cannot read handwritten form values — value recognition never exceeded **2.4%** over
  six engine configurations and four scales (ADR-015). Filled-form corpora are out of scope.
- Field reading on real notices sits at **58/60 clean, 0 wrong**; the 2 misses are genuinely
  blank fields, where abstaining is correct.
- The two corpora are not one population. e-GP notices are 98.9% born-digital; **CAG audit
  reports are 65.5%**, and OCR **abstained on 116 of the 153 pages** it attempted on them. The
  usable audit text is smaller than the page count suggests.
- Tesseract emits invalid UTF-8 bytes on Bengali pages. One byte makes every `/u` regex return
  null or false, silently — a page of 222 words reported 0. Sanitise recognised text before use.

## Hard-Won Rules

Each of these cost a debugging cycle. They generalise.

- **Relative spacing beats absolute thresholds in document layout.** Three separate defects came
  from one distance threshold trying to separate two different things — label-to-value gutter vs
  word spacing, value line vs next field. Compare a gap to the gaps around it.
- **Uniqueness must be enforced against the table, not the generator.** Faker's `unique()` knows
  only what it generated. When a factory feeds a table with unique constraints, the constraint
  list is the specification — not whichever column happened to fail first.
- **Emission order is not reading order.** `pdftotext` emits words in block order; sort by
  position before reasoning about a line.
- **Check the wiring, not just the class.** Four components so far were written, tested, and
  never called: `EgpTenderListingConnector` unreachable from the registry, `TenderObservationRecorder`
  never invoked by discovery, a listing cursor that only advanced so no notice was ever read twice,
  and the whole extraction spine, which no job ever reached. Tests passed in every case. **Unit
  tests verify components; only a live run verifies the wiring between them.**
- **A process started by launchd is not the shell you tested in.** It runs with a minimal `PATH`
  that excludes Homebrew, so a binary that resolves interactively is missing in the worker. The
  malware scanner appeared uninstalled for a full cycle after it had been installed, and every
  fetched artifact was quarantined as a result. Call external binaries by absolute path.
- **A signal that is constant ranks nothing.** A nonconformity score derived from the label
  position, or a page-level confidence shared by every field, cannot support calibration.
- **Dump the real data before theorising.** Every layout fix that worked came from printing
  coordinates; every one that came from reasoning about the layout was wrong.

## Intelligence Rules

- Thresholds and weights centralized in `intelligence_rules`; runs preserve threshold snapshots.
- Rule changes flow through the admin console/service layer and write `intelligence_rule_audits`.
- Every indicator carries source evidence, rule version, thresholds, payload, timestamp,
  severity, confidence, and review status.
- Peer comparison uses median/MAD, never mean/stddev, and refuses cohorts below 8.
- Conformal calibration is group-conditional and needs ≥ ⌈1/α⌉−1 per group (19 at α=0.05). A
  group below that flags nothing. The guarantee is bounded false-flag rate under *reviewer
  agreement*, never "certified corruption detection".

## Normalisation

Extracted fields map onto OCDS so records are comparable with other publishers. The raw value is
always kept beside the normalised one; an unrecognised code is reported unmapped, never guessed;
and no ocid is invented without a tender identifier.

## Production

`APP_DEBUG=false`, cached config/routes/views, supervised queue workers, scheduler, durable
storage, backups. `/healthz` is public-safe; `/version` exposes only deploy-safe metadata;
`/admin/system/metrics` stays behind analytics authorization. Requests carry correlation IDs.

## Git

- Short-lived feature branches. Do not amend unless asked. Do not revert user changes without
  approval. Non-interactive commands only.
- Conventional prefixes: `feat:`, `fix:`, `docs:`, `test:`, `chore:`.
- Commit messages explain why the change was needed and what the failure looked like.

| Branch | Purpose |
| --- | --- |
| `v2-ingestion` | live v2 pipeline: acquisition, extraction, OCR, calibration |
| `v2-integration` | v2 integration line (Stage 1–2 plus security fixes) |
| `v1.0.1-stabilization` | independently releasable v1 |
| `pre-v2-preservation-20260802` | earth-journey / orbital UI preservation |
| `build-civiclens-v1-foundation` | default branch, v1 history |

Worktrees: repo root is `pre-v2-preservation-20260802`; `.worktrees/v2-ingestion` is active v2
work. Branch names carry no `codex/` prefix (renamed 2026-08-08); PRs #1–#11 reference old names
in metadata, and those links no longer resolve though the diffs are intact.

## Definition Of Done

Implementation complete, tests passing where the environment allows, documentation synchronized,
architecture preserved, authorization enforced, performance considered, remaining debt written
down. Update `docs/` whenever behavior, schema, APIs, or architecture change.

## ADR Policy

Write an ADR when a durable architectural decision changes — not for routine work that follows
existing architecture. Accepted guardrails: Laravel, modular monolith, custom roles/permissions,
provider-agnostic search, normalized operational data, v2 separation, heterogeneous public
sources, geospatial privacy, reviewed publication, recognizer ceiling, certified flagging.

The conformal guarantee and the one input blocking it are written up in
`docs/adr/ADR-016-Certified-Flagging-And-Its-Label.md`. Read it before writing anything public
about the bound: it says what the bound covers, what it does not, and why reviewer adjudication
is the only admissible label.

## MCP Usage

Figma for design, Playwright for E2E, Context7 for library docs, Sequential Thinking for
planning, Cavemem for project memory. If one is unavailable, continue when safe and say which was
missing and why.

## Reference Documentation

Not inlined — 140 files, ~7,100 lines. Read on demand.

| Area | Path |
| --- | --- |
| Index of everything | `docs/MASTER_INDEX.md` |
| Constitution, charter, requirements | `docs/00`–`docs/02` |
| Architecture, database, search | `docs/03`–`docs/05` |
| Intelligence layer, UI, security | `docs/06`–`docs/08` |
| API, testing, deployment, devops | `docs/09`–`docs/12` |
| Performance, monitoring, scalability | `docs/13`–`docs/15` |
| Standards, git, AI development | `docs/16`–`docs/18` |
| Governance, open data, accessibility | `docs/19`–`docs/21` |
| Release, risk, roadmap, contributing | `docs/22`–`docs/25` |
| Glossary, FAQ, stack, principles, domain | `docs/26`–`docs/30` |
| Architecture decisions | `docs/adr/` |
| v2 blueprint, backlog, upgrade path | `docs/v2/` |
| Module detail | `docs/modules/*/README.md` |
