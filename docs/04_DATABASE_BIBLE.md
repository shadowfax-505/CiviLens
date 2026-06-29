# 04 Database Bible

## Database Philosophy

The database is the strongest part of CivicLens. V1 should demonstrate normalization, referential integrity, indexes, auditability, and query performance.

## Core Tables

- `users`, `roles`, `permissions`, `role_user`, `role_permission`
- `agencies`, `locations`, `projects`, `project_statuses`, `project_status_log`
- `budgets`, `budget_items`
- `procurements`, `procurement_items`, `suppliers`, `contracts`
- `documents`, `report_cards`, `comments`, `categories`, `entity_category`
- `activity_logs`, `notifications`, `settings`, `files`
- `llm_models`, `embeddings`, `anomaly_events`, `delay_alerts`, `contractor_scores`

## Index Strategy

- Index every foreign key.
- Add composite indexes for common filters such as `agency_id + status_id`, `location_id + status_id`, and `project_id + fiscal_year`.
- Use full-text search through Meilisearch for cross-resource search.

## Implemented in Sprint 1

- `users`
- `password_reset_tokens`
- `sessions`
- `cache`
- `jobs`
- `roles`
- `permissions`
- `role_user`
- `role_permission`
- `permission_groups`
- `account_activities`

## Implemented in Sprint 02

Geographic reference data is normalized into explicit administrative levels:

- `countries`
- `divisions`
- `districts`
- `upazilas`
- `unions`
- `wards`

Each geography table includes timestamps, soft deletes, indexed parent foreign keys, normalized unique constraints within its parent scope, nullable latitude/longitude, and nullable `geojson` for future GIS compatibility. Mapping, spatial indexes, and interactive map features remain out of scope for v1 Sprint 02.

Government agency registry tables:

- `agency_types`
- `agencies`
- `agency_user`

`agencies` supports hierarchical parent agencies, normalized agency type assignment, optional geography assignment from country through ward, contact details, status, and soft deletes. `agency_user` assigns users to agencies without replacing the existing role/permission system.

Sprint 02 added permission slugs:

- `locations.manage`
- `agencies.manage`

## Implemented in Sprint 03

Project Lifecycle Management adds normalized lookup tables and the core `projects` aggregate:

- `project_categories`
- `project_statuses`
- `project_priorities`
- `funding_sources`
- `fiscal_years`
- `projects`
- `project_activities`

`projects` references canonical Sprint 02 agency and geography tables instead of duplicating agency or location names. Project location assignment supports country, division, district, upazila, union, and ward foreign keys for future map and spatial-query work. Sprint 04 moves financial amount ownership into the Budget Engine; projects no longer author budget allocation or expenditure values.

Project lifecycle events are stored in `project_activities` with actor, event, old/new values, IP address, and user agent. Significant events currently include create, update, status change, progress update, archive, restore, and delete.

Project indexes support common filters:

- `agency_id + project_status_id`
- `project_category_id + project_priority_id`
- `funding_source_id + fiscal_year_id`
- geography hierarchy keys
- public/active/archive state
- planned date ranges
- approved budget and progress ranges

Sprint 04 removes project-owned financial amount usage. Budget amount filtering belongs to the Finance module.

## Implemented in Sprint 04

Financial Management adds the Budget Engine as the single source of truth for project finances:

- `budget_categories`
- `budget_types`
- `budget_statuses`
- `budget_transaction_types`
- `budgets`
- `budget_revisions`
- `budget_transactions`

`budgets` references `projects`, `fiscal_years`, `funding_sources`, configurable budget categories, budget types, and statuses. Current financial state is stored on budgets, while historical changes are append-only through revisions and transactions.

Financial calculations:

- Remaining balance = current allocation - reserved amount - committed amount - actual expenditure.
- Utilization % = actual expenditure / current allocation.
- Revisions preserve previous allocation, new allocation, difference, reason, approval date, and approver.
- Transactions are immutable; HTTP delete attempts return 405 and model deletion is blocked.

Indexes support project/fiscal year lookup, budget type/status filters, funding/category filters, amount ranges, active/archive state, and transaction/revision timelines.

## Identity Columns Added in Sprint 01

The `users` table now includes:

- `avatar_path`
- `is_active`
- `locked_at`
- `notification_preferences`
- `last_login_at`
- `password_changed_at`

Indexes were added for account state and account activity lookup:

- `users.is_active`
- `users.locked_at`
- `users.last_login_at`
- `account_activities.user_id + event`
- `account_activities.actor_id + event`

The custom role foundation seeds `admin`, `staff`, and `citizen` roles. See `docs/adr/ADR-007-Custom-Role-Permission-Foundation.md`.

## V2 Expansion Notes

Introduce AI tables only when the ingestion and review workflow exists. Keep AI outputs separate from source facts. Future GIS work may add spatial indexes, map tiles, and GeoJSON validation around the Sprint 02 geography tables without replacing the normalized hierarchy.
