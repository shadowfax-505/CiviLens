# Contractor Intelligence Platform Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build Sprint 06 Contractor Intelligence & Vendor Management as a normalized, searchable, auditable Laravel admin module.

**Architecture:** Extend the existing CivicLens modular monolith with a contractor domain that references canonical geography, users, procurement contracts, projects, agencies, and budgets. Keep controllers thin, place calculations/search/workflow in services, protect routes with a policy, and preserve historical records through append-only activity/blacklist/snapshot records.

**Tech Stack:** Laravel 13, Blade, Tailwind CSS, Pest, PHPStan/Larastan, Rector, Playwright.

---

### Task 1: Contractor Schema And Seed Foundation

**Files:**
- Create: `database/migrations/2026_06_30_000011_create_contractor_intelligence_tables.php`
- Create: `app/Models/Organization.php`
- Create: `app/Models/ContractorProfile.php`
- Create: contractor supporting models under `app/Models/`
- Create: contractor factories under `database/factories/`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Contractors/ContractorSeederTest.php`

- [ ] **Step 1: Write failing migration/seeder tests**

Create tests proving lookup/profile data seeds, required relationships exist, and historical records are append-only.

- [ ] **Step 2: Run targeted test**

Run: `php artisan test tests/Feature/Contractors/ContractorSeederTest.php`
Expected: FAIL because contractor tables/models do not exist yet.

- [ ] **Step 3: Implement normalized migration/models/factories/seeders**

Create lookup tables for company types, industries, contractor categories, classifications, registration statuses, risk levels, license types, certification types, compliance types, compliance statuses, and activity types. Create fact tables for organizations, branches, profiles, directors, contacts, licenses, certifications, insurance, compliance, legal cases, blacklist history, performance snapshots, and contractor activities.

- [ ] **Step 4: Run targeted test**

Run: `php artisan test tests/Feature/Contractors/ContractorSeederTest.php`
Expected: PASS.

### Task 2: Contractor Intelligence Services

**Files:**
- Create: `app/Services/Contractors/ContractorLifecycleService.php`
- Create: `app/Services/Contractors/ContractorListingService.php`
- Create: `app/Services/Contractors/ContractorDashboardService.php`
- Create: `app/Services/Contractors/ContractorScoreService.php`
- Create: `app/Events/ContractorRegistered.php`
- Create: `app/Events/LicenseExpiring.php`
- Create: `app/Events/CertificationExpiring.php`
- Create: `app/Events/ComplianceFailed.php`
- Create: `app/Events/RiskScoreUpdated.php`
- Create: `app/Events/PerformanceSnapshotCreated.php`
- Create: contractor queue jobs under `app/Jobs/`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Unit/Contractors/ContractorScoreServiceTest.php`
- Test: `tests/Feature/Contractors/ContractorWorkflowTest.php`

- [ ] **Step 1: Write failing service tests**

Test derived scores, registration activity, blacklist immutability, performance snapshot generation, event dispatch, and queue dispatch.

- [ ] **Step 2: Run targeted tests**

Run: `php artisan test tests/Unit/Contractors/ContractorScoreServiceTest.php tests/Feature/Contractors/ContractorWorkflowTest.php`
Expected: FAIL because services/events/jobs do not exist yet.

- [ ] **Step 3: Implement services/events/jobs**

Implement lifecycle operations, searchable listing query, dashboard aggregates, score calculations without persisting derived scores, domain events, and safe queued job scaffolds.

- [ ] **Step 4: Run targeted tests**

Run: `php artisan test tests/Unit/Contractors/ContractorScoreServiceTest.php tests/Feature/Contractors/ContractorWorkflowTest.php`
Expected: PASS.

### Task 3: Admin UI, Routes, Requests, Policies

**Files:**
- Create: `app/Http/Controllers/Admin/Contractors/OrganizationController.php`
- Create: `app/Http/Controllers/Admin/Contractors/ContractorProfileController.php`
- Create: `app/Http/Requests/Admin/Contractors/OrganizationRequest.php`
- Create: `app/Http/Requests/Admin/Contractors/ContractorProfileRequest.php`
- Create: `app/Policies/OrganizationPolicy.php`
- Modify: `routes/web.php`
- Modify: `resources/views/components/layouts/app.blade.php`
- Create: `resources/views/admin/contractors/organizations/index.blade.php`
- Create: `resources/views/admin/contractors/organizations/form.blade.php`
- Create: `resources/views/admin/contractors/organizations/show.blade.php`
- Create: `resources/views/admin/contractors/profiles/form.blade.php`
- Test: `tests/Feature/Contractors/ContractorManagementTest.php`

- [ ] **Step 1: Write failing admin feature tests**

Test authorization, dashboard/index access, create/update/archive/restore, filters, sorting, pagination, detail page, profile updates, validation, and unauthorized denial.

- [ ] **Step 2: Run targeted test**

Run: `php artisan test tests/Feature/Contractors/ContractorManagementTest.php`
Expected: FAIL because routes/controllers/views do not exist yet.

- [ ] **Step 3: Implement admin surface**

Add policies, FormRequests, routes, controllers, and Blade views using the existing CivicLens design language and URL-persistent filters.

- [ ] **Step 4: Run targeted test**

Run: `php artisan test tests/Feature/Contractors/ContractorManagementTest.php`
Expected: PASS.

### Task 4: Browser Tests And Documentation

**Files:**
- Modify: `tests/Browser/admin-workflows.spec.ts`
- Modify: `docs/04_DATABASE_BIBLE.md`
- Modify: `docs/09_API_SPECIFICATION.md`
- Modify: `docs/30_DOMAIN_MODEL.md`
- Create: `docs/modules/contractors/README.md`
- Modify: `docs/modules/README.md`
- Modify: `docs/MASTER_INDEX.md`
- Modify: `CHANGELOG.md`
- Modify: `.ai/PROJECT_MEMORY.md`
- Modify: `.ai/ARCHITECTURE_MEMORY.md`
- Modify: `.ai/CURRENT_SPRINT.md`
- Modify: `.ai/DECISIONS.md`

- [ ] **Step 1: Add browser coverage**

Extend Playwright admin workflows for contractor dashboard/search/create/detail responsive smoke tests.

- [ ] **Step 2: Update documentation**

Document schema, module behavior, routes, performance considerations, events/queues, and AI memory state.

- [ ] **Step 3: Run quality gate**

Run: `composer validate --strict`, `php artisan test`, `vendor/bin/pint --test`, `composer analyse`, `composer refactor:dry`, `npm run build`, `npm run test:e2e` where environment permits, and `git diff --check`.

---

## Self-Review

- Spec coverage: schema, relationships, derived intelligence, dashboards, search, UI, security, performance, events/queues, tests, and docs are all represented.
- Placeholder scan: no implementation step relies on unspecified architecture; exact files and verification commands are listed.
- Type consistency: contractor naming uses `Organization` as the root aggregate and `ContractorProfile` as the contractor-specific profile, matching the sprint language.
