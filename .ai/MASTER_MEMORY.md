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

## Sprint 11 Memory

Public Transparency is implemented as curated public web views over approved source records plus authenticated citizen report submission, UUID tracking, admin moderation, and append-only report activities. Public APIs, open-data exports, maps, OCR publication, semantic search, and AI-generated public summaries remain deferred.

## Sprint 12 Memory

Enterprise Procurement extends the existing tender core with procurement planning, bid opening privacy, immutable evaluation finalization, award approvals, contract payments, milestone acceptance, variation approval, closeout, enterprise procurement metrics, workflow events, and public-safe award notices. It remains additive to Sprint 05 and does not duplicate finance-owned budget facts.

## Sprint 13 Part 1 Memory

Production readiness adds Docker, production compose, Nginx, PHP-FPM, Supervisor, queue worker, scheduler loop, production PHP configuration, `.env.production.example`, and a public-safe `/healthz` endpoint. The Civic Integrity Engine remains deterministic: active rules run through existing intelligence services, `civic_intelligence_runs` stores reproducible threshold snapshots and run summaries, and generated indicators require human review. V1 still excludes OCR, LLMs, embeddings, vector databases, semantic search, AI agents, and legal conclusions.

## Sprint 13 Part 2 Memory

Production hardening extends, rather than rebuilds, Sprint 13 part 1. `/dashboard` is the Executive Command Center. `SystemMetricsService` powers `/healthz`, `/version`, and authorized `/admin/system/metrics`. `RequestCorrelation` attaches `X-Request-Id` to responses and log context. `RuleManagementService` owns audited rule updates, dry-runs, and execution metadata with `intelligence_rule_audits`. `IntelligenceDashboardService` now exposes timelines, distributions, rankings, and performance metrics. `ReportBuilder` renders CSV, spreadsheet-compatible, and PDF downloads from stored analytics report payloads. No v2 AI infrastructure was added.

## RC1 Memory

Release Candidate 1 validates CivicLens v1.0 as `v1.0.0-RC1`. RC hardening may change version metadata, defensive headers, documentation, release notes, checklists, and low-risk stability fixes only. It must not introduce new business modules, public API expansion, v2 AI infrastructure, OCR, LLMs, embeddings, vector search, semantic search, machine learning, recommendation systems, microservices, GraphQL, or experimental packages.

## Required Reading

- `docs/ENGINEERING_DIRECTIVE.md`
- `docs/MASTER_INDEX.md`
- `docs/00_PROJECT_CONSTITUTION.md`
- `docs/03_SYSTEM_ARCHITECTURE.md`
- `docs/04_DATABASE_BIBLE.md`
- `docs/v2/V2_UPGRADE_PATH.md`

## Implementation Directive

Before implementation, follow `docs/ENGINEERING_DIRECTIVE.md`. It defines mandatory reading order, database/search/security/testing expectations, and the definition of done.
