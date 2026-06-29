# Document Intelligence Platform Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build Sprint 07 Enterprise Document Management as a secure, versioned, searchable, auditable Laravel document platform.

**Architecture:** Extend CivicLens with a document domain that stores metadata in normalized tables while using Laravel Storage for file bytes. Documents are attached to projects, budgets, procurement records, contracts, contractors, agencies, geography, and future modules through polymorphic relationships, with immutable activities and versions preserving historical traceability.

**Tech Stack:** Laravel 13, Blade, Tailwind CSS, Laravel Storage, queues, Pest, PHPStan/Larastan, Rector, Playwright.

---

### Task 1: Document Schema And Seed Foundation

**Files:**
- Create: `database/migrations/2026_06_30_000012_create_document_management_tables.php`
- Create: document models under `app/Models/`
- Create: document factories under `database/factories/`
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `config/civiclens.php`
- Test: `tests/Feature/Documents/DocumentSeederTest.php`

- [ ] **Step 1: Write failing seeder/schema tests**

Cover lookup seed data, polymorphic relationship persistence, tag assignment, and immutable activity/version guards.

- [ ] **Step 2: Run targeted test**

Run: `php artisan test tests/Feature/Documents/DocumentSeederTest.php`
Expected: FAIL because document tables/models do not exist yet.

- [ ] **Step 3: Implement normalized migration/models/factories/seed data**

Create lookup tables for document types, categories, statuses, visibilities, permissions, and tags. Create documents, document versions, documentables, document permissions, document activities, OCR metadata, and AI metadata.

- [ ] **Step 4: Run targeted test**

Run: `php artisan test tests/Feature/Documents/DocumentSeederTest.php`
Expected: PASS.

### Task 2: Secure Storage, Versioning, Events, Queues

**Files:**
- Create: `app/Services/Documents/DocumentStorageService.php`
- Create: `app/Services/Documents/DocumentLifecycleService.php`
- Create: `app/Services/Documents/DocumentListingService.php`
- Create: `app/Services/Documents/DocumentDashboardService.php`
- Create: `app/Events/DocumentUploaded.php` if needed or extend existing event registration
- Create: `app/Events/DocumentUpdated.php`
- Create: `app/Events/DocumentArchived.php`
- Create: `app/Events/DocumentVersionCreated.php`
- Create: `app/Events/DocumentMetadataUpdated.php`
- Create: document processing jobs under `app/Jobs/`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Documents/DocumentWorkflowTest.php`

- [ ] **Step 1: Write failing workflow tests**

Cover upload, checksum, Storage fake, version replacement, download response, archive/restore, bulk actions, activity logging, event dispatch, and queued processing hooks.

- [ ] **Step 2: Run targeted test**

Run: `php artisan test tests/Feature/Documents/DocumentWorkflowTest.php`
Expected: FAIL because services/events/jobs do not exist yet.

- [ ] **Step 3: Implement storage/version lifecycle services**

Use Laravel Storage disks, validation-compatible file handling, checksum calculation, version history, immutable activities, and queued thumbnail/OCR/metadata/virus/search/AI hooks.

- [ ] **Step 4: Run targeted test**

Run: `php artisan test tests/Feature/Documents/DocumentWorkflowTest.php`
Expected: PASS.

### Task 3: Admin UI, Policy, Requests, Routes

**Files:**
- Create: `app/Policies/DocumentPolicy.php`
- Create: `app/Http/Requests/Admin/Documents/DocumentUploadRequest.php`
- Create: `app/Http/Requests/Admin/Documents/DocumentMetadataRequest.php`
- Create: `app/Http/Requests/Admin/Documents/DocumentBulkActionRequest.php`
- Create: `app/Http/Controllers/Admin/Documents/DocumentController.php`
- Create: `app/Http/Controllers/Admin/Documents/DocumentVersionController.php`
- Create: `app/Http/Controllers/Admin/Documents/DocumentBulkActionController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/components/layouts/app.blade.php`
- Create: document Blade views under `resources/views/admin/documents/`
- Test: `tests/Feature/Documents/DocumentManagementTest.php`

- [ ] **Step 1: Write failing admin tests**

Cover authorization, create/upload, update metadata, replace version, download, archive/restore, search/filter/sort/pagination, relationship attachment, permissions, and validation.

- [ ] **Step 2: Run targeted test**

Run: `php artisan test tests/Feature/Documents/DocumentManagementTest.php`
Expected: FAIL because routes/controllers/views do not exist yet.

- [ ] **Step 3: Implement admin surface**

Add dashboard/library/detail/upload/edit views, policies, requests, routes, controllers, and Blade UI consistent with CivicLens patterns.

- [ ] **Step 4: Run targeted test**

Run: `php artisan test tests/Feature/Documents/DocumentManagementTest.php`
Expected: PASS.

### Task 4: Browser Tests And Documentation

**Files:**
- Modify: `tests/Browser/admin-workflows.spec.ts`
- Modify: `docs/04_DATABASE_BIBLE.md`
- Modify: `docs/09_API_SPECIFICATION.md`
- Modify: `docs/30_DOMAIN_MODEL.md`
- Create: `docs/modules/documents/README.md`
- Modify: `docs/modules/README.md`
- Modify: `docs/MASTER_INDEX.md`
- Modify: `CHANGELOG.md`
- Modify: `.ai/PROJECT_MEMORY.md`
- Modify: `.ai/ARCHITECTURE_MEMORY.md`
- Modify: `.ai/CURRENT_SPRINT.md`
- Modify: `.ai/DECISIONS.md`

- [ ] **Step 1: Add browser coverage**

Extend Playwright admin workflows for document upload/search/detail/download/archive/restore responsive smoke tests.

- [ ] **Step 2: Update documentation**

Document schema, storage abstraction, versioning, permissions, events/queues, routes, performance, and AI-readiness.

- [ ] **Step 3: Run quality gate**

Run: `composer validate --strict`, `php artisan test`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse --debug --no-ansi --memory-limit=1G`, `vendor/bin/rector process --dry-run`, `npm run build`, `npm run test:e2e`, `git diff --check`, `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache`.

---

## Self-Review

- Spec coverage: schema, relationships, versioning, storage, upload/download/preview, bulk actions, search, dashboards, security, events/queues, tests, browser checks, and docs are represented.
- Placeholder scan: no task depends on unspecified architecture; exact files and commands are listed.
- Type consistency: naming uses `Document`, `DocumentVersion`, `DocumentActivity`, and `Documentable` consistently across schema/services/routes.
