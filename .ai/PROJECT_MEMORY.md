# Project Memory

## Current State

CivicLens has a Laravel 13 modular monolith foundation with the Enterprise v1.0 documentation skeleton, identity, geography, agencies, projects, finance, procurement, contractor intelligence, document management, and the Sprint 05.5 developer platform hardening layer.

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

## Next Milestone

Complete local/CI verification for Sprint 07, then plan Sprint 08 without starting it automatically.
