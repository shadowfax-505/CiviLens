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
