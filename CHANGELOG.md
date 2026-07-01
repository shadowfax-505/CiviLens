# Changelog

All notable changes to CivicLens are tracked here.

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
