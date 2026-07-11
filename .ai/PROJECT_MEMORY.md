# Project Memory

## Current State

CivicLens has a Laravel 13 modular monolith foundation with the Enterprise v1.0 documentation skeleton, identity, geography, agencies, projects, finance, procurement, contractor intelligence, document management, universal search, business intelligence analytics, intelligence readiness, public transparency, and Sprint 13 production hardening with a deterministic Civic Integrity Engine.

## Completed

- Repository map.
- Core handbook.
- V2 upgrade path.
- ADR structure.
- Sprint structure.
- Prompt templates.
- Laravel 13 application scaffold.
- Custom role and permission foundation.
- Seeded `admin`, `staff`, and `citizen` roles.
- Protected `/dashboard` route and placeholder `/login` route.
- Registration, login, logout, password reset, email verification, and password confirmation.
- Profile management, avatar upload, password changes, notification preferences, and account activity.
- Admin user listing, search, filters, sorting, pagination, status controls, lock controls, role assignment, and password reset.
- Sprint 02 geographic foundation: countries, divisions, districts, upazilas, unions/municipalities, and wards with normalized foreign keys, soft deletes, CRUD, search/filter/sort/pagination, policies, validation, factories, seed compatibility, and tests.
- Sprint 02 agency registry: agency types, hierarchical agencies, contact fields, geography assignment, user assignment, admin CRUD, search/filter/sort/pagination, policies, validation, factories, seed data, and tests.
- Sprint 03 project lifecycle management: normalized project lookup tables, projects, project activity audit trail, admin dashboard/search/detail/create/edit/archive/restore/delete workflows, policies, validation, factories, seed data, and tests.
- Sprint 04 financial management: Budget Engine with configurable budget categories/types/statuses/transaction types, budgets, revisions, immutable transactions, dashboard, archive/restore, search/filter/sort/pagination, policies, validation, factories, seed data, and tests.
- Sprint 05 procurement management: procurement methods, tender categories/statuses, tenders, bidder organizations, bid submissions, evaluation criteria/scores, awards, contracts, contract lifecycle tables, immutable procurement activities, dashboard/search/workspace UI, policies, validation, factories, seed data, and tests.
- Sprint 05.5 engineering hardening: GitHub Actions CI, Larastan/PHPStan level 8, Rector dry-run, Playwright browser test scaffolding, complexity metrics, event/listener logging, queued job scaffolds, observability helpers, Makefile, and synchronized developer documentation.
- Sprint 06 contractor intelligence: normalized organizations, contractor profiles, branches, directors, contacts, licenses, certifications, insurance, compliance, legal cases, immutable blacklist history, immutable performance snapshots, contractor activities, scoring services, events, queue jobs, admin UI, tests, and documentation.
- Sprint 07 document management: normalized document types/categories/statuses/visibilities/tags/permissions, document metadata, immutable versions, polymorphic relationships, activities, OCR and AI metadata preparation, secure Laravel Storage uploads/downloads, processing events/jobs, admin UI, tests, and documentation.
- Sprint 08 universal search: provider-agnostic `Searchable` and `SearchProvider` contracts, database provider, normalized search index/history/saved-search/click/analytics tables, ranking, suggestions, cache, queue-ready indexing, knowledge graph services, admin UI, JSON endpoints, tests, browser coverage, and documentation.
- Sprint 09 business intelligence: normalized analytics snapshots, reports, alert rules, alerts, dashboard states, analytics events, registry-driven metrics, dashboard services, chart-ready payloads, snapshots, report generation, rule-based alerts, admin UI, tests, browser coverage, and documentation.
- Sprint 10 intelligence readiness: normalized rule types, rules, advisory indicators, source-linked evidence, human reviews, processing jobs, append-only activities, rule execution services, review workflows, search/knowledge graph integration, admin UI, tests, browser coverage, and documentation.
- Sprint 11 public transparency: public portal, public project/agency/procurement/contractor/document/search views, safe document download routes, authenticated citizen report submission, UUID tracking, admin moderation, report activity audit trail, tests, browser coverage, and documentation.
- Sprint 12 enterprise procurement: procurement plans and approvals, bid opening records, immutable evaluation summaries, award approvals, contract payments, milestone acceptance, variation approvals, contract closeouts, procurement metrics, public-safe award notices, workflow events, admin routes, tests, and documentation.
- Sprint 13 part 1 production readiness and Civic Integrity Engine: production Docker/Nginx/PHP-FPM/Supervisor/Redis/MySQL scaffold, production environment example, public-safe health endpoint, scheduled `civiclens:integrity-run` command, reproducible `civic_intelligence_runs`, manual admin engine trigger, dashboard run status, repeat-winner and citizen-report-cluster rules, Pest tests, Playwright desktop/mobile/dark-mode/keyboard coverage, and documentation.
- Sprint 13 part 2 production hardening: Executive Command Center, enriched Integrity dashboard, audited rule-management console, rule dry-runs, expanded explainability, CSV/spreadsheet/PDF report downloads, version endpoint, authorized system metrics, request correlation IDs, updated Playwright coverage, and synchronized docs/memory.
- RC1 follow-up hardening: production Nginx now builds from the same release artifact as PHP-FPM so `public/build` cannot drift between Blade-rendered Vite manifests and edge-served assets. Figma file `https://www.figma.com/design/sV9RRgSjm1MB9OM6grywgN` captures the auth and application-shell polish direction implemented in Blade/Tailwind.
- V1 frontend modernization: Stitch MCP is exposed and project `projects/6475191169669847815` exists, but screen generation returned a Stitch OAuth credential error. CivicLens now uses a deploy-safe Blade/Tailwind design-token layer, polished application shell, profile appearance preference stored at `users.notification_preferences.appearance`, and `docs/31_FRONTEND_CUSTOMIZATION_GUIDE.md`.

## Next Milestone

CivicLens v1 is in Release Candidate 1 validation as `v1.0.0-RC1`. Do not begin v2 AI infrastructure until v1 deployment is complete and ADR-backed.
