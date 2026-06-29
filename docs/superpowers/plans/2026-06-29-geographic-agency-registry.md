# Geographic Foundation & Organization Registry Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build Sprint 02: a normalized geographic hierarchy and government agency registry that future projects, budgets, procurement, and map features can depend on.

**Architecture:** Extend the modular Laravel monolith without replacing Sprint 01 identity code. Geography is modeled as explicit normalized administrative tables from country to ward; agencies reference the most precise applicable geography level and support parent-child hierarchy plus user assignment. Controllers stay thin by delegating searchable listing behavior to query services and validation to form requests.

**Tech Stack:** Laravel 13, Blade, Tailwind CSS, custom CivicLens roles/permissions, policies, Pest, SQLite test database with MySQL-compatible schema choices where practical.

---

## Files

- Create migrations for `countries`, `divisions`, `districts`, `upazilas`, `unions`, `wards`, `agency_types`, `agencies`, and `agency_user`.
- Create models under `app/Models`: `Country`, `Division`, `District`, `Upazila`, `AdministrativeUnion`, `Ward`, `AgencyType`, `Agency`.
- Modify `app/Models/User.php` with agency relationship.
- Create factories for every new model.
- Modify `database/seeders/DatabaseSeeder.php` to seed geography, agency permissions, agency types, and a baseline agency.
- Create policies: `ManageReferenceDataPolicy` and `AgencyPolicy`.
- Modify `app/Providers/AppServiceProvider.php` to register policies.
- Create form requests under `app/Http/Requests/Admin/Geography` and `app/Http/Requests/Admin`.
- Create controllers under `app/Http/Controllers/Admin/Geography` and `app/Http/Controllers/Admin`.
- Create query services under `app/Services/Geography` and `app/Services/Agencies`.
- Modify `routes/web.php`.
- Create Blade views for geography and agency admin screens.
- Add Pest feature/unit tests for relationships, authorization, CRUD, validation, search/filter/sort/pagination.
- Update `docs/04_DATABASE_BIBLE.md`, `docs/09_API_SPECIFICATION.md`, `docs/30_DOMAIN_MODEL.md`, new module docs, and AI memory files.

## Tasks

### Task 1: Failing Geography Tests

- [x] Add Pest tests proving admins can create the full hierarchy, search/filter/sort/paginate listings, validation rejects invalid parent records, non-admin users are forbidden, and deletes soft-delete records.
- [x] Run the new tests and verify they fail because geography models/routes/controllers do not exist.

### Task 2: Geography Schema and Domain

- [x] Add normalized migrations with foreign keys, soft deletes, unique constraints, indexes, nullable coordinates, and nullable GeoJSON placeholders.
- [x] Add models, factories, relationships, policy registration, form requests, controllers, query service, routes, and Blade views.
- [x] Run geography tests until green.

### Task 3: Failing Agency Tests

- [x] Add Pest tests proving admins can manage hierarchical agencies, assign location and users, filter/search/sort/paginate listings, enforce validation, forbid non-admin users, and soft-delete agencies.
- [x] Run the new tests and verify they fail because agency registry code does not exist.

### Task 4: Agency Schema and Domain

- [x] Add agency migrations, models, factories, policies, request validation, query service, controller, routes, and Blade views.
- [x] Add agency and location permissions to configuration and seeders without hardcoded permission strings.
- [x] Run agency tests until green.

### Task 5: Documentation and Verification

- [x] Update Database Bible, API Specification, Domain Model, module docs, sprint memory, project memory, next steps, and changelog.
- [x] Run `composer test`.
- [x] Run `vendor/bin/pint --test`.
- [x] Run `npm run build`.
- [x] Review git status and summarize Sprint 02 without starting Sprint 03.

