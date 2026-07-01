# CivicLens

[![CI](https://github.com/muttakinrahman/civiclens/actions/workflows/ci.yml/badge.svg)](https://github.com/muttakinrahman/civiclens/actions/workflows/ci.yml)

CivicLens is an AI-assisted Civic Intelligence Platform for collecting, validating, searching, analyzing, and visualizing public-sector project information.

This repository contains the **Enterprise v1.0 Laravel platform** plus the project documentation set. The current application includes authentication, role and permission management, user administration, geography, agencies, projects, budgets, procurement and contracts, contractors, documents, universal search, analytics, public transparency, citizen reports, production deployment scaffolding, and the deterministic Civic Integrity Engine.

V1 does not implement OCR, LLMs, embeddings, vector databases, semantic search, autonomous AI agents, or legal conclusions. Integrity signals are deterministic, evidence-backed, and human-reviewed.

## Repository Map

- `.ai/` - persistent AI memory, rules, sprint state, and operating checklists.
- `docs/` - engineering handbook, architecture, database, API, security, testing, and roadmap.
- `docs/adr/` - Architecture Decision Records.
- `docs/api/` - detailed REST API resource docs.
- `docs/database/` - schema and migration planning.
- `docs/modules/` - per-domain module documentation.
- `docs/sprints/` - sprint plans for v1 delivery.
- `docs/v2/` - upgrade path from v1 to a stronger v2 platform.
- `app/` - Laravel controllers, models, policies, services, and middleware.
- `database/` - migrations, factories, and seeders for implemented modules.
- `resources/views/` - Blade views for auth, dashboard, profile, and admin workflows.
- `tests/` - Pest feature/unit coverage and Playwright browser specifications.
- `tools/quality/` - lightweight repository health and maintainability tooling.
- `docker/production/` - Nginx, PHP-FPM, Supervisor, and production PHP configuration.

## Production Readiness

- `/dashboard` provides the authenticated Executive Command Center.
- `/admin/intelligence` provides the Civic Integrity dashboard and explainability views.
- `/admin/intelligence/rules` provides audited rule management and dry-run review.
- `/admin/analytics/reports` generates CSV, spreadsheet-compatible, and PDF reports.
- `/healthz` exposes public-safe readiness checks.
- `/version` exposes deploy metadata without secrets.
- `/admin/system/metrics` exposes authorized operational metrics.
- `docker-compose.production.yml` is the baseline production stack for app, worker, Nginx, MySQL, and Redis.

## Development Commands

- `composer test` - run the Laravel/Pest test suite.
- `vendor/bin/pint --test` - verify Laravel formatting.
- `composer analyse` - run PHPStan/Larastan at level 8 for the application layer.
- `composer refactor:dry` - run the safe Rector dry-run profile.
- `composer metrics` - run the local complexity report.
- `composer quality` - run the main PHP quality gate.
- `npm run build` - build frontend assets.
- `npm run test:e2e` - run Playwright browser tests after browser binaries are installed.

## Version Strategy

Version 1 is intentionally structured so v2 can be added without rewriting the project:

- V1 documents define stable names, boundaries, tables, and APIs.
- V2 ideas live in `docs/v2/` until promoted by ADR.
- Every major feature should link back to `docs/MASTER_INDEX.md`.
- Backward-compatible database changes are preferred.
- Breaking changes require a new ADR and migration notes.

## Start Here

1. Read [docs/ENGINEERING_DIRECTIVE.md](docs/ENGINEERING_DIRECTIVE.md).
2. Read [docs/MASTER_INDEX.md](docs/MASTER_INDEX.md).
3. Read [.ai/MASTER_MEMORY.md](.ai/MASTER_MEMORY.md) before using an AI coding assistant.
4. Review [.ai/PROJECT_MEMORY.md](.ai/PROJECT_MEMORY.md) and [.ai/NEXT_STEPS.md](.ai/NEXT_STEPS.md) for the current implementation state.
5. Run `composer quality`, `npm run build`, and applicable browser tests before claiming a change is complete.
