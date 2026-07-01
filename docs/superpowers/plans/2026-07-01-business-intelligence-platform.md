# Business Intelligence Platform Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build Sprint 09 as a reusable analytics platform with executive dashboards, metrics, snapshots, reports, alerts, charts, events, tests, and synchronized documentation.

**Architecture:** Add an Analytics module that reads normalized operational data and stores only analytics-specific snapshots, report definitions, alerts, dashboard states, and audit events. Controllers call analytics services; operational modules do not calculate BI metrics directly.

**Tech Stack:** Laravel 13, Blade, Tailwind CSS, Pest, Chart.js-ready JSON chart definitions, Laravel Cache, Events, Queues.

---

### Task 1: Operating Manual And Sprint Plan

**Files:**
- Create: `AGENTS.md`
- Create: `docs/superpowers/plans/2026-07-01-business-intelligence-platform.md`

- [x] **Step 1: Confirm `AGENTS.md` status**

Run: `test -f AGENTS.md`

Expected: non-zero exit means the file is missing and must be created.

- [x] **Step 2: Add repository operating manual**

Create the root operating manual with mission, architecture, coding standards, database rules, quality gates, documentation sync, security, performance, ADR policy, and MCP usage policy.

- [x] **Step 3: Save this implementation plan**

Create this plan under `docs/superpowers/plans/`.

### Task 2: Analytics Schema

**Files:**
- Create: `database/migrations/2026_07_01_000014_create_business_intelligence_tables.php`
- Create: `app/Models/AnalyticsSnapshotPeriod.php`
- Create: `app/Models/AnalyticsSnapshot.php`
- Create: `app/Models/AnalyticsReport.php`
- Create: `app/Models/AnalyticsAlertRule.php`
- Create: `app/Models/AnalyticsAlert.php`
- Create: `app/Models/DashboardState.php`
- Create: `app/Models/AnalyticsEvent.php`

- [ ] **Step 1: Write failing schema tests**

Create Pest tests that assert analytics tables exist, have key columns, enforce relationships, and preserve immutable snapshot/alert history.

- [ ] **Step 2: Run schema tests**

Run: `php artisan test tests/Feature/Analytics/AnalyticsSchemaTest.php`

Expected: fail because tables and models do not exist.

- [ ] **Step 3: Add migration and models**

Create normalized analytics tables with foreign keys to users and snapshot periods. Keep payload fields JSON where they represent calculated point-in-time metrics or filter definitions.

- [ ] **Step 4: Run schema tests again**

Run: `php artisan test tests/Feature/Analytics/AnalyticsSchemaTest.php`

Expected: pass.

### Task 3: Registry And Metric Services

**Files:**
- Create: `app/Support/Analytics/AnalyticsFilters.php`
- Create: `app/Support/Analytics/MetricResult.php`
- Create: `app/Support/Analytics/ChartDefinition.php`
- Create: `app/Services/Analytics/MetricRegistry.php`
- Create: `app/Services/Analytics/MetricEngine.php`
- Create: `app/Services/Analytics/AggregationEngine.php`
- Create: `app/Services/Analytics/DashboardService.php`
- Create: `app/Services/Analytics/TrendAnalysisService.php`
- Create: `app/Services/Analytics/ForecastPreparationService.php`
- Create: `app/Services/Analytics/ReportBuilder.php`
- Create: `app/Services/Analytics/SnapshotService.php`
- Create: `app/Services/Analytics/InsightService.php`
- Create: `app/Services/Analytics/ChartService.php`

- [ ] **Step 1: Write failing service tests**

Create unit tests for metric registration, metric calculation, dashboard composition, chart definitions, snapshots, reports, and alerts.

- [ ] **Step 2: Implement support objects and services**

Implement registry-driven metrics across projects, budgets, procurement, contractors, documents, search, users, agencies, geography, platform health, and future AI readiness.

- [ ] **Step 3: Run service tests**

Run: `php artisan test tests/Unit/Analytics`

Expected: pass.

### Task 4: Authorization, Events, Jobs, And Seed Data

**Files:**
- Create: `app/Policies/AnalyticsPolicy.php`
- Create: `app/Events/MetricCalculated.php`
- Create: `app/Events/DashboardViewed.php`
- Create: `app/Events/SnapshotGenerated.php`
- Create: `app/Events/ReportGenerated.php`
- Create: `app/Events/AlertTriggered.php`
- Create: `app/Events/InsightGenerated.php`
- Create: `app/Events/AnalyticsCacheRefreshed.php`
- Create: `app/Jobs/GenerateAnalyticsSnapshot.php`
- Create: `app/Jobs/GenerateAnalyticsReport.php`
- Create: `database/factories/AnalyticsSnapshotFactory.php`
- Create: `database/factories/AnalyticsReportFactory.php`
- Create: `database/factories/AnalyticsAlertRuleFactory.php`
- Create: `database/factories/AnalyticsAlertFactory.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `config/civiclens.php`

- [ ] **Step 1: Write failing authorization and event tests**

Assert admins can view analytics, users without permissions cannot, events dispatch, and queue jobs can be pushed.

- [ ] **Step 2: Implement policy, permission seed data, events, jobs, and factories**

Add `analytics.manage` to configuration and seed data without hardcoded IDs.

- [ ] **Step 3: Run authorization/event tests**

Run: `php artisan test tests/Feature/Analytics/AnalyticsAuthorizationTest.php`

Expected: pass.

### Task 5: UI, Controllers, Routes, And API

**Files:**
- Create: `app/Http/Controllers/Admin/Analytics/AnalyticsDashboardController.php`
- Create: `app/Http/Controllers/Admin/Analytics/AnalyticsMetricController.php`
- Create: `app/Http/Controllers/Admin/Analytics/AnalyticsReportController.php`
- Create: `app/Http/Controllers/Admin/Analytics/AnalyticsSnapshotController.php`
- Create: `app/Http/Controllers/Admin/Analytics/AnalyticsAlertController.php`
- Create: `app/Http/Requests/Admin/Analytics/AnalyticsFilterRequest.php`
- Create: `resources/views/admin/analytics/dashboard.blade.php`
- Create: `resources/views/admin/analytics/partials/kpi-card.blade.php`
- Create: `resources/views/admin/analytics/partials/chart-panel.blade.php`
- Create: `resources/views/admin/analytics/reports.blade.php`
- Create: `resources/views/admin/analytics/alerts.blade.php`
- Modify: `resources/views/components/layouts/app.blade.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write failing feature tests**

Assert dashboard, metric JSON, report export, snapshot generation, and alert pages enforce authorization and return expected content.

- [ ] **Step 2: Implement controllers, requests, routes, and views**

Use Blade/Tailwind, URL-persistent filters, Chart.js-ready payloads, accessible cards, dark mode classes, and reusable partials.

- [ ] **Step 3: Run feature tests**

Run: `php artisan test tests/Feature/Analytics/AnalyticsDashboardTest.php`

Expected: pass.

### Task 6: Documentation And Verification

**Files:**
- Create: `docs/modules/analytics/README.md`
- Modify: `docs/MASTER_INDEX.md`
- Modify: `docs/03_SYSTEM_ARCHITECTURE.md`
- Modify: `docs/04_DATABASE_BIBLE.md`
- Modify: `docs/09_API_SPECIFICATION.md`
- Modify: `docs/13_PERFORMANCE_GUIDE.md`
- Modify: `docs/14_MONITORING.md`
- Modify: `docs/30_DOMAIN_MODEL.md`
- Modify: `.ai/MASTER_MEMORY.md`
- Modify: `.ai/PROJECT_MEMORY.md`
- Modify: `.ai/ARCHITECTURE_MEMORY.md`
- Modify: `.ai/CURRENT_SPRINT.md`
- Modify: `.ai/DECISIONS.md`
- Modify: `.ai/NEXT_STEPS.md`
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Update docs and AI memory**

Document analytics tables, services, routes, authorization, performance, monitoring, and future expansion notes.

- [ ] **Step 2: Run quality gates**

Run the sprint quality gate commands and record any environment blockers.

- [ ] **Step 3: Final review**

Confirm architecture remains consistent and no Sprint 10 work has started.
