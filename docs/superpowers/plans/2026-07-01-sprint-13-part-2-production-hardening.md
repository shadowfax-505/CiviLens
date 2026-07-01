# Sprint 13 Part 2 Production Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete CivicLens v1 production hardening with an executive command center, richer Civic Integrity dashboards, rule management, reporting exports, monitoring endpoints, and synchronized documentation.

**Architecture:** Extend the existing Laravel modular monolith. Keep controllers thin, add service classes for dashboard/report/monitoring composition, extend existing Intelligence and Analytics models, and keep all calculations deterministic and evidence-backed.

**Tech Stack:** Laravel 13, Blade, Tailwind CSS, Pest, Playwright, MySQL/SQLite-compatible migrations, Laravel queues/scheduler/cache/storage.

**Implementation status:** Completed in Sprint 13 part 2. The plan was executed test-first with additive services, migrations, views, browser coverage, and documentation synchronization.

---

### Task 1: Executive Command Center

**Files:**
- Create: `app/Services/Executive/ExecutiveDashboardService.php`
- Create: `app/Http/Controllers/Admin/ExecutiveDashboardController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/dashboard.blade.php`
- Test: `tests/Feature/Executive/ExecutiveDashboardTest.php`

- [ ] **Step 1: Write failing tests**

```php
it('shows an authorized executive command center with cross-module KPIs', function (): void {
    $admin = executiveAdmin();
    $this->actingAs($admin)->get('/dashboard')
        ->assertOk()
        ->assertSee('Executive Command Center')
        ->assertSee('System Health')
        ->assertSee('Recent Integrity Runs');
});
```

- [ ] **Step 2: Verify red**

Run: `php artisan test tests/Feature/Executive/ExecutiveDashboardTest.php`
Expected: fail because the command center content/service does not exist yet.

- [ ] **Step 3: Implement dashboard service/controller/view**

Create a service that returns cards for projects, budgets, tenders, contractors, citizen reports, documents, indicators, search volume, latest health, recent activities, top agencies, top contractors, recent alerts, and recent integrity runs. Replace the closure `/dashboard` route with `ExecutiveDashboardController`.

- [ ] **Step 4: Verify green**

Run: `php artisan test tests/Feature/Executive/ExecutiveDashboardTest.php`
Expected: pass.

### Task 2: Civic Integrity Dashboard Enhancements

**Files:**
- Modify: `app/Services/Intelligence/IntelligenceDashboardService.php`
- Modify: `resources/views/admin/intelligence/dashboard.blade.php`
- Modify: `app/Services/Intelligence/ExplainabilityService.php`
- Test: `tests/Feature/Intelligence/CivicIntegrityDashboardHardeningTest.php`

- [ ] **Step 1: Write failing tests**

```php
it('returns integrity run history distributions rankings and explainability details', function (): void {
    $admin = civicIntegrityHardeningAdmin();
    $this->actingAs($admin)->get('/admin/intelligence')
        ->assertOk()
        ->assertSee('Integrity Timeline')
        ->assertSee('Rule Execution History')
        ->assertSee('Agency Risk Ranking');
});
```

- [ ] **Step 2: Verify red**

Run: `php artisan test tests/Feature/Intelligence/CivicIntegrityDashboardHardeningTest.php`
Expected: fail because the enriched panels are missing.

- [ ] **Step 3: Implement deterministic dashboard sections**

Extend `summary()` with run timeline, rule execution history, severity/status/module distributions, agency/contractor/project/budget/document/citizen-report rankings, rule duration placeholders, and source-backed explanation payloads.

- [ ] **Step 4: Verify green**

Run: `php artisan test tests/Feature/Intelligence/CivicIntegrityDashboardHardeningTest.php`
Expected: pass.

### Task 3: Rule Management Console

**Files:**
- Create: `database/migrations/2026_07_01_000019_extend_intelligence_rules_for_management.php`
- Create: `app/Models/IntelligenceRuleAudit.php`
- Create: `app/Http/Requests/Admin/Intelligence/UpdateIntelligenceRuleRequest.php`
- Create: `app/Services/Intelligence/RuleManagementService.php`
- Modify: `app/Models/IntelligenceRule.php`
- Modify: `app/Http/Controllers/Admin/Intelligence/IntelligenceRuleController.php`
- Modify: `routes/web.php`
- Create: `resources/views/admin/intelligence/rules/index.blade.php`
- Create: `resources/views/admin/intelligence/rules/show.blade.php`
- Test: `tests/Feature/Intelligence/IntelligenceRuleManagementTest.php`

- [ ] **Step 1: Write failing tests**

```php
it('allows authorized admins to update rule thresholds and records audit history', function (): void {
    $admin = civicIntegrityHardeningAdmin();
    $rule = IntelligenceRule::factory()->create(['thresholds' => ['warning' => 2]]);
    $this->actingAs($admin)->patch("/admin/intelligence/rules/{$rule->id}", [
        'is_active' => false,
        'priority' => 25,
        'weight' => 80,
        'severity_default' => 'warning',
        'thresholds' => '{"warning":4,"critical":8}',
        'description' => 'Updated deterministic rule documentation.',
        'documentation_url' => 'https://example.com/rules/repeat-winner',
        'execution_frequency' => 'daily',
    ])->assertRedirect();
    expect($rule->refresh()->is_active)->toBeFalse()
        ->and(IntelligenceRuleAudit::query()->where('intelligence_rule_id', $rule->id)->exists())->toBeTrue();
});
```

- [ ] **Step 2: Verify red**

Run: `php artisan test tests/Feature/Intelligence/IntelligenceRuleManagementTest.php`
Expected: fail because the migration/request/service/routes/views are missing.

- [ ] **Step 3: Implement console**

Add rule management columns, audit records, validation, service-owned updates, index/show/update/dry-run routes, and Blade views.

- [ ] **Step 4: Verify green**

Run: `php artisan test tests/Feature/Intelligence/IntelligenceRuleManagementTest.php`
Expected: pass.

### Task 4: Reporting Hardening

**Files:**
- Modify: `app/Services/Analytics/ReportBuilder.php`
- Modify: `app/Http/Controllers/Admin/Analytics/AnalyticsReportController.php`
- Modify: `resources/views/admin/analytics/reports.blade.php`
- Test: `tests/Feature/Analytics/ProductionReportExportTest.php`

- [ ] **Step 1: Write failing tests**

```php
it('generates downloadable csv excel and pdf-compatible reports with export history', function (): void {
    $admin = analyticsHardeningAdmin();
    foreach (['csv', 'xlsx', 'pdf'] as $format) {
        $this->actingAs($admin)->post('/admin/analytics/reports', [
            'dashboard' => 'executive',
            'format' => $format,
        ])->assertRedirect();
    }
    expect(AnalyticsReport::query()->count())->toBe(3);
});
```

- [ ] **Step 2: Verify red**

Run: `php artisan test tests/Feature/Analytics/ProductionReportExportTest.php`
Expected: fail for unsupported formats or missing metadata.

- [ ] **Step 3: Implement report formats**

Use existing `AnalyticsReport` records. Generate CSV immediately, generate tab-delimited spreadsheet-compatible output for `xlsx`, and generate text/html PDF-compatible output with branding and evidence sections without adding external packages.

- [ ] **Step 4: Verify green**

Run: `php artisan test tests/Feature/Analytics/ProductionReportExportTest.php`
Expected: pass.

### Task 5: Monitoring and Version Endpoints

**Files:**
- Create: `app/Http/Controllers/VersionController.php`
- Create: `app/Http/Controllers/Admin/SystemMetricsController.php`
- Create: `app/Http/Middleware/RequestCorrelation.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/HealthCheckController.php`
- Test: `tests/Feature/Monitoring/ProductionMonitoringTest.php`

- [ ] **Step 1: Write failing tests**

```php
it('exposes public safe version and enriched health data', function (): void {
    $this->getJson('/version')->assertOk()->assertJsonStructure(['app', 'version', 'environment', 'generated_at']);
    $this->getJson('/healthz')->assertOk()->assertJsonPath('checks.scheduler.configured', true);
});
```

- [ ] **Step 2: Verify red**

Run: `php artisan test tests/Feature/Monitoring/ProductionMonitoringTest.php`
Expected: fail because `/version` and scheduler/metrics fields are missing.

- [ ] **Step 3: Implement monitoring**

Add request/correlation ID middleware, version endpoint, admin metrics endpoint, scheduler/integrity/database/cache/storage fields, and structured response headers.

- [ ] **Step 4: Verify green**

Run: `php artisan test tests/Feature/Monitoring/ProductionMonitoringTest.php`
Expected: pass.

### Task 6: Browser Coverage, Docs, and Gates

**Files:**
- Modify: `tests/Browser/production-readiness.spec.ts`
- Modify: `AGENTS.md`
- Modify: `README.md`
- Modify: docs listed in the Sprint 13 Part 2 request
- Modify: `.ai/*.md`
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Add Playwright tests**

Cover `/dashboard`, `/admin/intelligence/rules`, report downloads, `/version`, mobile and dark-mode rendering.

- [ ] **Step 2: Run quality gates**

Run the Sprint 13 Part 2 required commands:

```bash
composer validate --strict
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
vendor/bin/rector process --dry-run
npm run build
npm run test:e2e
git diff --check
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize:clear
```

- [ ] **Step 3: Final review**

Confirm no v2 AI/OCR/embedding/vector/semantic work was introduced, no duplicate services were created, and all docs match behavior.
