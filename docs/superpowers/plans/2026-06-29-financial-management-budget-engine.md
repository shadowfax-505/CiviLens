# Financial Management & Budget Engine Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build Sprint 04: a normalized Budget Engine that is the single source of truth for project financial information.

**Architecture:** Add a dedicated finance domain attached to `projects.id`. Budgets own allocations, commitments, expenditures, revisions, and transactions; Projects keep lifecycle metadata and reference budgets through relationships instead of storing duplicated budget totals. Financial history is immutable for transactions and append-only for revisions, with archive/restore on budgets rather than deleting financial records.

**Tech Stack:** Laravel 13, Blade, Tailwind CSS, custom CivicLens policies/permissions, Pest, SQLite test database with MySQL-compatible schema design where practical.

---

## Tasks

### Task 1: Failing Finance Tests

- [x] Add tests for budget CRUD, calculations, revision management, transactions, archive/restore, immutable transaction delete protection, search/filter/sort/pagination, authorization, validation, relationships, factories, and seeders.
- [x] Add tests or adjust project tests proving project financial values are no longer authored on project records.
- [x] Run tests and verify they fail because finance classes/routes/tables do not exist.

### Task 2: Schema, Models, Factories

- [x] Add finance lookup tables: budget categories, budget types, budget statuses, budget transaction types.
- [x] Add budgets, budget revisions, and budget transactions with indexes and foreign keys.
- [x] Remove active project financial amount columns from the Project model/request/factory/UI/search path.
- [x] Add models, factories, relationships, casts, and immutable transaction guard.

### Task 3: Policies, Validation, Services, Routes

- [x] Add budget policy using existing custom role/permission foundation.
- [x] Add request validation for budgets, revisions, and transactions.
- [x] Add `BudgetCalculationService`, `BudgetLifecycleService`, and `BudgetListingService`.
- [x] Add admin budget routes and thin controllers.

### Task 4: UI

- [x] Add budget dashboard with summary cards, utilization, revision count, monthly trend table, and financial health.
- [x] Add budget index/detail/forms with filters, timeline, revision history, transaction history, archive/restore, and empty states.
- [x] Add navigation for authorized users.

### Task 5: Docs and Verification

- [x] Seed finance lookup data and baseline budget.
- [x] Update Database Bible, Domain Model, Finance module docs, API spec, changelog, and AI memory.
- [x] Run `composer test`, `vendor/bin/pint --test`, and `npm run build`.

