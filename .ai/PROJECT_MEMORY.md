# Project Memory

## Current State

CivicLens has a Laravel 13 foundation with the Enterprise v1.0 documentation skeleton, local environment defaults, role/permission schema, protected dashboard route, and Sprint 01 web Identity & Access Management.

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

## Next Milestone

Install Sanctum when package access is available, then implement token API auth or continue to Sprint 05 procurement planning.
