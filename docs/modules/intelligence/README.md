# Intelligence Module

## Responsibility

Prepare for AI-assisted analysis while keeping source records authoritative, explainable, and human-reviewed.

## V1 Scope

Sprint 10 implements rule-based intelligence readiness without real OCR extraction, LLM calls, embeddings, vector search, or automated legal conclusions.

Implemented capabilities:

- Configurable intelligence rule types and rules.
- Reproducible Civic Integrity Engine runs with engine version, threshold snapshots, run status, and generated-indicator counts.
- Advisory indicators with severity, confidence, source references, and rule versions.
- Evidence records linked to source data through polymorphic references.
- Human review workflow for pending, in-review, accepted, dismissed, and needs-more-evidence states.
- Processing job readiness for future OCR, AI review, and search synchronization.
- Admin dashboard, indicator review queue, processing queue, events, jobs, policies, factories, seeders, tests, and documentation.

Indicators are advisory until reviewed by a human. The module must never label people, agencies, contractors, or projects as corrupt.

## Sprint 13 Part 1 Rules

The Civic Integrity Engine runs active rules through `CivicIntegrityEngineService` and `RuleExecutionService`.

New deterministic rule slugs:

- `procurement-repeat-winner-concentration` identifies bidders with concentrated approved-award counts above configured thresholds.
- `citizen-report-cluster` identifies projects with unresolved citizen-report clusters above configured thresholds.

Both rules store detection payloads, evidence links, rule versions, and threshold context for review. They identify review signals only and do not make legal conclusions.

## Operations

`POST /admin/intelligence/engine/run` lets authorized administrators run the engine manually. The `civiclens:integrity-run` Artisan command is scheduled daily for production. Dashboard summaries show engine run counts and the latest run status.

## V2 Notes

Add real OCR extraction, embeddings, semantic search, and model-assisted risk scoring behind the existing evidence and review interfaces.
