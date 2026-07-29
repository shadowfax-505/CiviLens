# Changelog

All notable changes to CivicLens are tracked here.

## [Unreleased]

- Added the rollout-guarded, exact nine-stage Earth-to-Bangladesh journey with pinned local Cesium assets, validated local NASA imagery, resilient three-layer fallback behavior, weekly review-only MODIS candidate preparation, and desktop/mobile Playwright coverage.
- Changed public search pagination to stream the bounded indexed result window, preserving visibility filtering without materializing the full window in memory.
- Added role-aware shared navigation/search, safer document downloads, and a staff change-request workflow for projects, documents, contractors, procurement, and agencies.
- Added admin user creation with temporary passwords, role assignment, and unverified-by-default accounts that are routed to email verification.
- Refined the change-request flow so staff submit requests and admins review them while editing records directly.
- Added project map assignment workflows, embedded geographic location sections on project create/edit forms, and reusable map display on project detail pages.

## [1.0.0] - 2026-07-12

- Finalized the deterministic CivicLens v1 release surface without adding v2 AI features.
- Added bounded admin/public portfolio maps with filter parity, GeoJSON markers, clustering, coordinate-pair and geography-chain validation, and portable tile configuration.
- Added additive MySQL 8.4 location and intelligence-run schema support with reversible indexes, deterministic candidate ordering, dry-run cap metadata, and corrected low-utilization scoring.
- Hardened Docker deployment with separate web, worker, scheduler, and one-shot release roles, external managed-service support, authenticated bundled Redis, durable object-storage configuration, and separate ingress readiness.
- Synchronized v1.0.0 documentation, release checklist, deployment strategy, and AI memory.
- Remediated validated deep-scan findings with per-document bulk authorization, protected citizen-report access and attachments, public child-record filtering, bounded/throttled public search, and contractor profile visibility gates.

## [1.0.0-RC1] - 2026-07-04

- Added About-first public navigation, avatar settings/logout menu, Leaflet/OpenStreetMap geography coordinate picking, document/avatar upload drop zones, relationship filters for countries, realtime analytics snapshot/CSV actions, and safer intelligence dry-run/engine behavior.
- Promoted CivicLens v1.0 to Release Candidate 1 metadata with `APP_VERSION=v1.0.0-RC1`.
- Added baseline defensive security headers for application responses.
- Added visible footer release metadata.
- Added RC1 release checklist and release notes.
- Validated the RC1 Docker runtime locally with PHP 8.4, MySQL 8.4, Redis 7.4, Nginx, Supervisor queue workers, scheduler, health/version/metrics endpoints, authentication, analytics, search, procurement, public portal, queue processing, and integrity execution.
- Hardened the production image by excluding stale Laravel bootstrap cache files, repairing runtime storage ownership for mounted volumes, installing the Redis extension, and aligning PHP requirements with PHP `^8.4.1`.
- Fixed MySQL migration portability by shortening long generated index names.
- Hardened analytics metric caching to store portable arrays instead of serialized value objects.
- Aligned Nginx edge security headers and added Supervisor control status support.
- Fixed production asset drift by building Nginx from the same release artifact as PHP-FPM instead of serving host-mounted `public/build` files.
- Added Figma-guided Blade polish for the shared shell and authentication screens, including stronger responsive layout, focus states, dark-mode surfaces, and production-grade form styling.
- Added Stitch-guided CivicLens frontend modernization through a deploy-safe Blade/Tailwind shell, design tokens, profile appearance setting, and frontend customization guide.
- Aligned production Docker Compose database credentials with explicit `MYSQL_*` variables and documented `--env-file .env.production` deployment usage.
- Synchronized release, deployment, monitoring, security, database, API, and AI memory documentation for production-readiness validation.

## [0.1.0] - 2026-06-29

- Created Enterprise v1.0 documentation repository skeleton.
- Added AI memory, engineering handbook, ADR structure, API/database docs, sprint plans, and prompt templates.
- Added v2 upgrade path so v1 decisions can evolve without large rewrites.
- Scaffolded Laravel 13 application foundation.
- Added custom role and permission schema, models, factories, seed data, and tests.
- Added protected dashboard route and placeholder login route.
- Added Sprint 01 web Identity & Access Management: registration, login, logout, password reset, email verification, password confirmation, profile management, avatar upload, notification preferences, account activity, admin user management, role assignment, and account locks.
- Added Pest and migrated verification to Pest-compatible tests.
- Added Sprint 02 Geographic Foundation and Organization Registry: normalized country-to-ward hierarchy, agency types, hierarchical agencies, agency-user assignments, admin CRUD screens, policies, validation, factories, seed data, tests, and documentation.
- Added Sprint 03 Project Lifecycle Management: normalized project lookup tables, projects, lifecycle activity logging, admin dashboard/search/detail/forms, archive/restore/delete workflows, policies, validation, factories, seed data, tests, and documentation.
- Fixed identity account activity logging to write `user_agent`, matching the existing migration.
- Added Sprint 04 Financial Management & Budget Engine: normalized budget lookups, budgets, revisions, immutable transactions, financial dashboard, budget search, archive/restore, policies, validation, factories, seed data, tests, and documentation.
- Moved project financial amount ownership to budgets by removing active project allocation/expenditure fields from Project code paths.
- Added Sprint 05 Procurement & Tender Management: normalized procurement lookups, tenders, lots, bidders, bids, documents, evaluation committees, scoring, awards, contracts, contract lifecycle tables, immutable procurement timeline, dashboard/search UI, policies, validation, factories, seed data, tests, and documentation.
- Added Sprint 05.5 Engineering Hardening: GitHub Actions CI, Larastan/PHPStan level 8 configuration, conservative Rector dry-run profile, Playwright browser test configuration, local complexity metrics, domain events/listeners, queued job scaffolds, storage/observability guidance, developer commands, and updated engineering documentation.
- Added Sprint 06 Contractor Intelligence & Vendor Management: normalized contractor organization/profile schema, branch/contact/director/credential/compliance/legal/blacklist/performance/activity records, contractor scoring services, lifecycle events, queue jobs, admin dashboard/search/forms/detail pages, policies, validation, factories, seed data, tests, browser coverage, and module documentation.
- Added Sprint 07 Enterprise Document Management: normalized document metadata, tags, permissions, polymorphic relationships, immutable versions, activities, OCR/AI metadata preparation, secure Laravel Storage uploads/downloads, processing events/jobs, admin dashboard/library/detail/preview/version workflows, bulk actions, tests, browser coverage, and module documentation.
- Added Sprint 08 Universal Search & Knowledge Discovery: provider-agnostic `Searchable`/`SearchProvider` contracts, database search provider, normalized search index/analytics/history/saved-search tables, ranking, suggestions, cache, queue-ready indexing, knowledge graph services, admin search UI, JSON endpoints, browser coverage, ADR-008, and synchronized documentation.
- Added Sprint 09 Business Intelligence & Analytics: normalized analytics snapshots, reports, alert rules, alerts, dashboard states, analytics events, registry-driven KPI services, executive dashboard UI, chart-ready payloads, snapshot/report queues, rule-based alerts, tests, browser coverage, AGENTS.md, and synchronized documentation.
- Added Sprint 10 Intelligence Readiness: normalized intelligence rules, indicators, evidence, reviews, processing jobs, activities, rule execution services, review workflow, search/knowledge graph integration, admin UI, queued preparation jobs, tests, browser coverage, and synchronized documentation.
- Added Sprint 11 Public Transparency & Citizen Engagement: public portal, public project/agency/procurement/document/search views, safe document downloads, authenticated citizen report submission, UUID tracking, admin moderation, report activity audit trail, tests, browser coverage, and synchronized documentation.
- Added Sprint 12 Enterprise Procurement, Tender & Contract Lifecycle Platform: procurement plans, bid opening privacy, immutable evaluation summaries, award approvals, contract payments, milestone acceptance, variation approvals, closeout records, enterprise procurement metrics, public-safe award notices, search registration, workflow events, admin workflow routes, tests, and synchronized documentation.
- Added Sprint 13 Part 1 Production Readiness & Civic Integrity Engine: production Docker/Nginx/PHP-FPM/Supervisor/Redis/MySQL scaffold, production environment example, public-safe health endpoint, scheduled deterministic integrity engine runs, reproducible engine run history, repeat-winner and citizen-report-cluster indicators, dashboard run status, Pest coverage, Playwright desktop/mobile/dark-mode/keyboard coverage, and synchronized documentation.
- Added Sprint 13 Part 2 Enterprise Production Hardening & Civic Intelligence Completion: Executive Command Center, enriched Civic Integrity dashboard, audited rule-management console, deterministic rule dry-runs, expanded indicator explainability, CSV/spreadsheet/PDF report downloads, deploy-safe version endpoint, authorized system metrics endpoint, request correlation middleware, updated Playwright coverage, and synchronized documentation/AI memory.
