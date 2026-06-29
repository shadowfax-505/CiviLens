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

Introduce AI tables only when the ingestion and review workflow exists. Keep AI outputs separate from source facts.
