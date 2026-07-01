# Master Memory

## Project

CivicLens is an AI-assisted Civic Intelligence Platform. V1 is a Laravel data platform for civic projects, agencies, budgets, procurements, suppliers, documents, reports, search, analytics, intelligence readiness, and audit logs.

## Core Rule

Build v1 as a stable, database-first platform. Store advanced ideas in `docs/v2/` until promoted through an ADR.

## Stack

Laravel, PHP, MySQL, Redis, Laravel Scout, Meilisearch, Tailwind CSS, Livewire, Pest/PHPUnit, Docker or Laravel Sail.

## Sprint 09 Memory

Business Intelligence is implemented as an Analytics module. Metrics are registered through `MetricRegistry`, calculated through `MetricEngine`, composed through `DashboardService`, and persisted only as snapshots, reports, alerts, dashboard states, and analytics events where historical reproducibility or auditability requires it.

## Sprint 10 Memory

Intelligence Readiness is implemented as deterministic rules, advisory indicators, source-linked evidence, human reviews, processing preparation jobs, and append-only activities. V1 does not run real OCR, LLM calls, embeddings, vector search, or automated legal conclusions.

## Required Reading

- `docs/ENGINEERING_DIRECTIVE.md`
- `docs/MASTER_INDEX.md`
- `docs/00_PROJECT_CONSTITUTION.md`
- `docs/03_SYSTEM_ARCHITECTURE.md`
- `docs/04_DATABASE_BIBLE.md`
- `docs/v2/V2_UPGRADE_PATH.md`

## Implementation Directive

Before implementation, follow `docs/ENGINEERING_DIRECTIVE.md`. It defines mandatory reading order, database/search/security/testing expectations, and the definition of done.
