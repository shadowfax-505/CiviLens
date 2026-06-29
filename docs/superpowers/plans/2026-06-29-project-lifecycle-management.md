# Project Lifecycle Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build Sprint 03: normalized Project Lifecycle Management as the core CivicLens business module.

**Architecture:** Extend the existing modular Laravel monolith. Projects reference Sprint 02 agencies and geography instead of duplicating agency/location names, lookup values live in normalized tables, lifecycle events are recorded in a dedicated project activity log, and searchable listing logic is isolated in a service so future Meilisearch/Livewire integration can reuse the same filters. UI follows the current Blade/Tailwind design system because Livewire is not installed in the repository.

**Tech Stack:** Laravel 13, Blade, Tailwind CSS, custom CivicLens roles/permissions, policies, form requests, Pest, SQLite test database with MySQL-compatible schema design where practical.

---

## Files

- Create migrations for project lookup tables, `projects`, and `project_activity_logs`.
- Create models: `Project`, `ProjectCategory`, `ProjectStatus`, `ProjectPriority`, `FundingSource`, `FiscalYear`, `ProjectActivity`.
- Create factories for all new models.
- Modify `User` and `Agency` with project relationships.
- Create `ProjectPolicy`.
- Register project policy in `AppServiceProvider`.
- Create `ProjectRequest`.
- Create services: `ProjectListingService`, `ProjectLifecycleService`.
- Create `Admin\ProjectController`.
- Add admin project routes and navigation.
- Create Blade views for project dashboard/index, detail, create/edit form, and archived list.
- Add Pest feature tests for CRUD, authorization, validation, search/filter/sort/pagination, archive/restore/delete, status/progress activity logging.
- Add Pest unit tests for relationships, factories, and seeders.
- Update Database Bible, Domain Model, Projects module docs, API spec, changelog, and AI memory files.

## Tasks

### Task 1: Failing Project Feature Tests

- [x] Add tests proving non-admins cannot access project administration.
- [x] Add tests proving admins can create, view, update, archive, restore, and delete projects.
- [x] Add tests proving advanced search filters, sorting, pagination, date/budget/progress ranges, and geography filters work.
- [x] Add tests proving validation rejects invalid lookup/relationship fields.
- [x] Run the tests and verify they fail because project code does not exist yet.

### Task 2: Schema, Models, Factories

- [x] Add normalized lookup/project/activity migrations.
- [x] Add models, factories, relationships, fillable/casts, and soft deletes.
- [x] Add project relationships to User and Agency.

### Task 3: Policies, Validation, Services, Routes

- [x] Add `ProjectPolicy` using existing custom admin role authorization.
- [x] Add `ProjectRequest` for create/update validation.
- [x] Add `ProjectListingService` for database-backed advanced filtering.
- [x] Add `ProjectLifecycleService` for create/update/archive/restore/delete and activity logging.
- [x] Add routes and controller actions.

### Task 4: Blade/Tailwind UI

- [x] Add project admin dashboard/index with filters, table, empty state, and archive links.
- [x] Add project detail view with metadata, budget/progress/status, activity timeline, and lifecycle actions.
- [x] Add create/edit forms using existing design system classes and accessible labels.
- [x] Add navigation to Projects for authorized users.

### Task 5: Seeders, Unit Tests, Documentation, Verification

- [x] Seed project permissions and lookup data without hardcoded permission references outside config.
- [x] Add unit tests for relationships, factories, and seed data.
- [x] Update documentation and AI memory.
- [x] Run `composer test`, `vendor/bin/pint --test`, and `npm run build`.

