# CivicLens V1 Implementation Plan

## Goal

Build a stable Laravel v1 foundation that can evolve into CivicLens v2 without rewriting the core domain model.

## Phase 0: Repository Readiness

- Confirm `.gitignore`, `.env.example`, setup docs, ADRs, and sprint docs exist.
- Keep `.ai/`, `docs/`, and `prompts/` intact when scaffolding Laravel.

## Phase 1: Laravel Foundation

- Scaffold Laravel. Status: complete.
- Configure `.env`.
- Install authentication stack. Status: protected route exists; full auth UI remains.
- Decide and install role/permission strategy. Status: custom role/permission foundation accepted.
- Add first tests. Status: dashboard access and role/permission tests added.

## Phase 2: Core Data Model

- Implement users, roles, permissions.
- Implement agencies and locations.
- Implement projects and project status history.
- Add factories and seeders.

## Phase 3: Civic Operations

- Implement budgets and budget items.
- Implement procurements, procurement items, suppliers, and contracts.
- Implement documents and reports.
- Add audit logging.

## Phase 4: Search and Analytics

- Integrate Laravel Scout and Meilisearch.
- Index projects and document metadata.
- Add database-backed filters.
- Add admin analytics dashboard.

## Phase 5: V1 Hardening

- Expand tests.
- Review policies and validation.
- Review query performance.
- Update docs and changelog.
- Move discovered future work to `docs/v2/V2_BACKLOG.md`.

## V2 Migration Principles

- Prefer additive migrations.
- Version public APIs.
- Keep source facts separate from AI-derived signals.
- Promote v2 features through ADRs only.
