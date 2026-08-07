@AGENTS.md

## Session Rules

- Model alias: `opus`.
- Verify repository state before implementing. Never trust a handoff summary as fact.
- Never merge v2 work into `v1.0.1-stabilization`. v1 stays independently releasable.
- Feature branches → PR into `v2-ingestion` (pipeline work) or `v2-integration`. No push/merge/tag/deploy without an explicit approval gate.
- Additive, reversible migrations only. Never rewrite historical migrations.
- Thin controllers, Form Request validation, Policy authorization, logic in services/actions/query builders.
- Never claim tests, scans, browser checks, PRs, or deployments passed without pasted evidence.
- Prove a fix works by stashing it and showing the new test fails, then restoring.
- Preserve unrelated user changes and local generated files (`.codebase-memory/`, screenshots, `.superpowers/`).

## Product Guardrails

CivicLens never declares corruption, guilt, fraud, or legal wrongdoing. Keep source facts,
extracted text, candidates, deterministic indicators, reviews, subject responses, and public
projections as separate layers. Human review is mandatory before any public integrity conclusion.
Never collect, store, hash, or rank on NID / government identity documents.

Enabling a source endpoint or crawling a live publisher requires a human-recorded robots/terms
`access_decision` on `source_endpoints`. General authorization does not substitute for that review.

## Branches

Branch names carry no `codex/` prefix — renamed 2026-08-08. PRs #1–#11 reference the old names
in their metadata; those links no longer resolve, but the PRs and diffs are intact.

| Branch | Purpose |
| --- | --- |
| `v2-ingestion` | live v2 pipeline: acquisition, extraction, OCR, calibration |
| `v2-integration` | v2 integration line (Stage 1–2 plus security fixes) |
| `v1.0.1-stabilization` | independently releasable v1 |
| `pre-v2-preservation-20260802` | earth-journey / orbital UI preservation |
| `build-civiclens-v1-foundation` | default branch, v1 history |

## Worktrees

| Path | Purpose |
| --- | --- |
| repo root | `pre-v2-preservation-20260802` |
| `.worktrees/v2-ingestion` | active v2 pipeline work |

## Extraction Toolchain

`poppler-utils` and `tesseract-ocr` with `ben`+`eng` data are hard runtime dependencies.
CI installs both and asserts the `ben` language is present, so a missing toolchain fails
loudly rather than silently skipping tests.

## Detailed Plans

Live under `docs/`, `docs/v2/`, `.ai/`, and `docs/adr/`. Do not inline long plans here.
