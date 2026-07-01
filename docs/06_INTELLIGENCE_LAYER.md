# 06 Intelligence Layer

## Policy

AI assists analysis. It must not accuse individuals or organizations of corruption. It may identify explainable risk indicators backed by data.

## V1 Scope

V1 implements deterministic intelligence readiness and deterministic civic integrity analysis, not machine learning. Sprint 10 adds rule-based indicators, evidence packages, human review workflows, processing job readiness, and audit timelines. Sprint 13 part 1 adds the Civic Integrity Engine, which runs active rules as a reproducible batch and records threshold snapshots.

Implemented v1 intelligence outputs are advisory. They must include source records, rule version, detection timestamp, severity, confidence, and review status.

V1 does not perform real OCR extraction, LLM calls, embedding generation, vector search, or automated legal conclusions.

## Civic Integrity Engine

`CivicIntegrityEngineService` runs active `IntelligenceRule` records through deterministic services. Each engine run stores:

- Engine version.
- Run status and timestamps.
- Triggering user when applicable.
- Rules executed and indicators created.
- Threshold and configuration snapshot.
- Summary payload for review.

Sprint 13 part 1 introduces two additional rule slugs:

- `procurement-repeat-winner-concentration` detects concentrated approved-award patterns by bidder count.
- `citizen-report-cluster` detects multiple unresolved citizen reports linked to the same project.

These rules identify signals for human review only. They must not describe the signal as corruption, fraud, guilt, or legal wrongdoing.

## V2 Candidate Features

- OCR for uploaded public documents.
- Delay risk indicators.
- Budget variance alerts.
- Supplier reliability scoring.
- Semantic search over extracted documents.

## Explainability Requirement

Every intelligence insight must include source records, rule/model version, timestamp, threshold context, evidence links, and human review status.
