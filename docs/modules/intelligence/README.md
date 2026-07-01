# Intelligence Module

## Responsibility

Prepare for AI-assisted analysis while keeping source records authoritative, explainable, and human-reviewed.

## V1 Scope

Sprint 10 implements rule-based intelligence readiness without real OCR extraction, LLM calls, embeddings, vector search, or automated legal conclusions.

Implemented capabilities:

- Configurable intelligence rule types and rules.
- Advisory indicators with severity, confidence, source references, and rule versions.
- Evidence records linked to source data through polymorphic references.
- Human review workflow for pending, in-review, accepted, dismissed, and needs-more-evidence states.
- Processing job readiness for future OCR, AI review, and search synchronization.
- Admin dashboard, indicator review queue, processing queue, events, jobs, policies, factories, seeders, tests, and documentation.

Indicators are advisory until reviewed by a human. The module must never label people, agencies, contractors, or projects as corrupt.

## V2 Notes

Add real OCR extraction, embeddings, semantic search, and model-assisted risk scoring behind the existing evidence and review interfaces.
