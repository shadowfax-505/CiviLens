# Intelligence Module

## Responsibility

Prepare for AI-assisted analysis while keeping source records authoritative, explainable, and human-reviewed.

## V1 Scope

Sprint 10 implements rule-based intelligence readiness without real OCR extraction, LLM calls, embeddings, vector search, or automated legal conclusions.

Implemented capabilities:

- Configurable intelligence rule types and rules.
- Reproducible Civic Integrity Engine runs with engine version, threshold snapshots, run status, and generated-indicator counts.
- Audited rule management for active state, priority, weight, thresholds, severity, descriptions, documentation URLs, execution frequency, dry-run estimates, and latest execution metadata.
- Shared candidate queries for execution and dry-run estimates, with finite non-negative threshold validation, ordered warning/critical thresholds, and bounded percentage thresholds.
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

Both rules store detection payloads, evidence links, rule versions, and threshold context for review. They identify review signals only and do not make legal conclusions. Low budget utilization is calculated as expenditure divided by current allocation; lower utilization produces a higher deterministic risk score without changing the stored utilization payload.

## Operations

`POST /admin/intelligence/engine/run` lets authorized administrators run the engine manually. The `civiclens:integrity-run` Artisan command is scheduled daily for production. Dashboard summaries show engine run counts, latest run status, integrity timelines, rule execution history, indicator distribution, agency/contractor/project/budget/document/citizen-report/geography rankings, and performance metrics.

`/admin/intelligence/rules` is the production rule-management console. Updates are validated and written through `RuleManagementService`, then recorded in `intelligence_rule_audits`. Candidate execution is capped at 25 stably ordered records per rule. Dry-runs estimate matching source records without creating indicators, evidence, reviews, or activities; their structured payload retains the full estimate and adds the execution cap, capped candidate count, and truncation state. HTML dry-runs retain the existing visible status message.

Engine web runs record failed run metadata safely and return user-safe feedback instead of leaking raw database or stack-trace details.

`ExplainabilityService` exposes triggered rules, threshold context, actual/expected values, source records, supporting evidence, calculation timestamps, engine version, recommendations, deterministic confidence, and human review requirements.

## V2 Notes

Add real OCR extraction, embeddings, semantic search, and model-assisted risk scoring behind the existing evidence and review interfaces.
