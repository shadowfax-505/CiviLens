# Engineering Hardening & Developer Platform Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Strengthen CivicLens engineering quality, CI, static analysis, browser testing, observability, queues, storage abstraction, documentation, and developer experience without changing business functionality.

**Architecture:** Preserve the modular Laravel monolith and avoid business workflow changes. Add tool configuration, event/job infrastructure, CI checks, developer docs, and safe performance/observability helpers around existing modules.

**Tech Stack:** Laravel 13, Pest, Pint, PHPStan/Larastan, Rector, Playwright, GitHub Actions, Composer scripts, NPM scripts, Laravel queues, Laravel Storage, structured logging.

---

## Tasks

### Task 1: Baseline and Tooling

- [ ] Verify current Composer/NPM dependencies and scripts.
- [ ] Add PHPStan/Larastan, Rector, and Playwright dependency declarations.
- [ ] Add `phpstan.neon`, `rector.php`, `playwright.config.ts`, browser test bootstrap, and quality scripts.
- [ ] Run dependency installation where possible.

### Task 2: CI/CD

- [ ] Add GitHub Actions workflow for Composer validation, install, tests, Pest, Pint, PHPStan/Larastan, NPM build, and cache optimization.
- [ ] Add dependency caching for Composer and NPM.
- [ ] Add browser test workflow steps with Playwright browser installation.

### Task 3: Engineering Infrastructure

- [ ] Add safe domain events for existing lifecycle moments without changing behavior.
- [ ] Add queued job placeholders for notifications, reports, exports, OCR, AI processing, and search indexing.
- [ ] Add observability helpers for performance timing and structured logging context.
- [ ] Confirm file upload paths use Laravel Storage configuration instead of hardcoded providers.

### Task 4: Browser Tests and Quality Reporting

- [ ] Add Playwright tests for auth, dashboard, CRUD/search/filter/pagination, authorization, and responsive layouts.
- [ ] Add quality documentation for dead-code detection, complexity, and maintainability thresholds.
- [ ] Add repository health notes for intentionally deferred tools.

### Task 5: Documentation and Verification

- [ ] Update Testing Strategy, DevOps Guide, Deployment Guide, Performance Guide, Monitoring Guide, Contributing Guide, README, Changelog, and AI memory.
- [ ] Run Composer validation, Pest/full tests, Pint, PHPStan/Larastan, Rector dry-run, Playwright tests, and NPM build where dependencies are available.
- [ ] Document any blocked verification caused by unavailable network/package installation.
