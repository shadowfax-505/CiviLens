# 04 Database Bible

## Database Philosophy

The database is the strongest part of CivicLens. V1 should demonstrate normalization, referential integrity, indexes, auditability, and query performance.

## Core Tables

- `users`, `roles`, `permissions`, `role_user`, `role_permission`
- `countries`, `divisions`, `districts`, `upazilas`, `unions`, `wards`
- `agencies`, `agency_types`, `agency_user`
- `projects`, `project_categories`, `project_statuses`, `project_priorities`, `project_activities`
- `budgets`, `budget_revisions`, `budget_transactions`
- `procurement_plans`, `tenders`, `bid_submissions`, `awards`, `contracts`, `contract_milestones`, `variation_orders`
- `organizations`, `contractor_profiles`, `compliance_records`, `legal_cases`, `blacklist_histories`, `contractor_performance_snapshots`
- `documents`, `document_versions`, `documentables`, `document_permissions`, `document_activities`
- `search_indexes`, `search_history`, `saved_searches`, `search_jobs`
- `analytics_snapshots`, `analytics_reports`, `analytics_alerts`, `dashboard_states`, `analytics_events`
- `intelligence_rules`, `intelligence_indicators`, `intelligence_evidence`, `intelligence_reviews`, `civic_intelligence_runs`, `intelligence_rule_audits`
- `citizen_reports`, `citizen_report_activities`
- `change_requests`, `change_request_activities`
- `user_district_preferences`, `user_notification_preferences`

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

Portfolio GIS keeps `projects.latitude` and `projects.longitude` as the authoritative location facts. A later additive migration creates nullable `projects.location` as an SRID 4326 MySQL `POINT` and a B-tree `(latitude, longitude, id)` index; SQLite and other portable test/runtime drivers retain the latitude/longitude query path. The `POINT` is synchronized from a complete coordinate pair and is never used to fabricate a location.

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

## Implemented in Sprint 05

Procurement & Tender Management adds the procurement core and preserves a full tender-to-contract history:

- `procurement_methods`
- `tender_categories`
- `tender_statuses`
- `bidder_organizations`
- `tenders`
- `tender_lots`
- `bid_submissions`
- `bid_documents`
- `evaluation_committees`
- `committee_members`
- `evaluation_criteria`
- `evaluation_scores`
- `awards`
- `contracts`
- `contract_milestones`
- `variation_orders`
- `contract_extensions`
- `liquidated_damages`
- `completion_certificates`
- `procurement_activities`

`tenders` reference `projects`, `budgets`, `agencies`, procurement methods, categories, and statuses. `contracts` reference awards, winning bids, projects, and budgets. Contract records intentionally do not duplicate budget allocations or expenditure totals; finance remains the source of truth.

Timeline integrity is provided by `procurement_activities`. Activity records are append-only and model deletion is blocked. Significant events include tender create/update/publish/close/archive/restore, bid submission, evaluation scoring, award creation, and contract creation.

Indexes support tender number lookup, project/budget joins, agency/status filters, method/category filters, published/closing date ranges, active/archive state, bidder lookup, contract status summaries, and activity timelines.

## Implemented in Sprint 12

Enterprise Procurement extends the Sprint 05 tender core without replacing existing source tables:

- `procurement_plans`
- `procurement_plan_activities`
- `bid_opening_records`
- `bid_withdrawals`
- `bid_compliance_items`
- `evaluation_summaries`
- `award_approvals`
- `contract_deliverables`
- `contract_payments`
- `contract_closeouts`

Sprint 12 also adds an optional `organization_id` contractor registry link to `bidder_organizations`; bid amount, validity, security, proposal summary, opening, and withdrawal fields to `bid_submissions`; public disclosure status to `awards`; acceptance/completion metadata to `contract_milestones`; and approved amount, schedule extension, and reason fields to `variation_orders`.

Procurement plans reference agencies, budgets, projects, fiscal years, funding sources, and procurement methods. Bidder organizations auto-link to Sprint 06 contractor organizations when registration numbers match. Bid opening records preserve the first auditable opening timestamp and recorded amount. Evaluation summaries are immutable finalization records derived from evaluation scores. Award approvals, payments, milestone acceptance, variation approvals, and closeouts preserve lifecycle history without duplicating finance-owned budget facts.

Indexes support plan approval status, agency/fiscal-year planning, bid opening lookup, evaluation finalization lookup, contract payment timelines, and closeout reporting.

## Implemented in Sprint 06

Contractor Intelligence & Vendor Management adds normalized organization, compliance, legal, credential, performance, and lifecycle records:

- `organization_company_types`
- `organization_industries`
- `contractor_categories`
- `contractor_classifications`
- `contractor_registration_statuses`
- `contractor_risk_levels`
- `license_types`
- `certification_types`
- `compliance_types`
- `compliance_statuses`
- `contractor_activity_types`
- `organizations`
- `branch_offices`
- `contractor_profiles`
- `directors`
- `contact_people`
- `contractor_licenses`
- `contractor_certifications`
- `insurance_policies`
- `compliance_records`
- `legal_cases`
- `blacklist_histories`
- `contractor_performance_snapshots`
- `contractor_activities`

Organizations reference canonical company type, industry, geography, and creator/updater users. Contractor profiles reference normalized category, classification, registration status, and risk level. Performance snapshots reference contractor profile, project, contract, agency, and budget, preserving immutable completed-contract history for analytics and future AI review.

Blacklist histories, performance snapshots, and contractor activities are append-only through model deletion guards. Derived contractor scores are calculated by services and are not persisted as source-of-truth values.

Indexes support organization search, registration lookup, company/industry/geography filters, contractor risk/status filters, license/certification expiry, compliance review dates, legal case status, performance timelines, and contractor activity timelines.

## Implemented in Sprint 07

Enterprise Document Management adds the normalized document platform:

- `document_types`
- `document_categories`
- `document_statuses`
- `document_visibilities`
- `document_permission_types`
- `document_tags`
- `documents`
- `document_versions`
- `documentables`
- `document_tag`
- `document_permissions`
- `document_activities`
- `document_ocr_metadata`
- `document_ai_metadata`

`documents` stores metadata and private Laravel Storage references only. File bytes stay behind the configured storage disk, and authorized downloads stream through application routes instead of exposing internal paths.

`document_versions` preserves every replacement with version number, checksum, storage path, uploader, reason, and current-version marker. Previous versions are retained and model deletion is blocked for versions and document activities.

`documentables` provides polymorphic attachments to projects, budgets, tenders, contracts, contractors, organizations, agencies, countries, divisions, districts, upazilas, unions, and wards. This avoids duplicate attachment tables while preserving normalized references to source domain records.

Search indexes support title, UUID, filename, extension, MIME type, checksum, status, visibility, type, category, owner, uploader, archive state, creation date, version lookups, tag search, and document timeline queries.

OCR and AI metadata tables are intentionally nullable preparation tables. Processing output remains separate from source document facts until future AI sprints implement reviewed workflows.

## Implemented in Sprint 08

Universal Search & Knowledge Discovery adds normalized platform search tables:

- `search_indexes`
- `search_documents`
- `search_keywords`
- `search_synonyms`
- `search_popularity`
- `search_clicks`
- `saved_searches`
- `search_history`
- `search_jobs`

`search_indexes` references source records with `searchable_type` and `searchable_id`. Source domain tables remain authoritative. Indexed display text, keywords, metadata, and AI-preparation fields exist only to support discovery and provider synchronization.

AI-ready nullable fields include:

- `embedding_reference`
- `semantic_hash`
- `entity_summary`
- `search_vector`
- `entity_keywords`
- `last_embedding_update`

Indexes support source lookup, module filtering, visibility filtering, status filtering, keyword suggestions, click analytics, saved searches, history timelines, and queued indexing operations.

Sprint 08 added permission slug:

- `search.manage`

## Implemented in Sprint 09

Business Intelligence & Analytics adds normalized analytics infrastructure tables:

- `analytics_snapshot_periods`
- `analytics_snapshots`
- `analytics_reports`
- `analytics_alert_rules`
- `analytics_alerts`
- `dashboard_states`
- `analytics_events`

`analytics_snapshots` stores immutable periodic dashboard payloads by period, dashboard, date, and filter hash. Snapshots preserve calculated metric and chart outputs but do not replace operational source records.

`analytics_reports` stores generated report metadata, filters, dashboard payloads, format, status, and expiration. Sprint 13 part 2 renders lightweight CSV, spreadsheet-compatible, and PDF downloads from stored dashboard payloads, branding, filters, KPIs, charts, and evidence metadata.

`analytics_alert_rules` stores configurable rule-based thresholds. `analytics_alerts` stores triggered warnings and is immutable. AI-generated alerts are not implemented in v1.

`dashboard_states` stores user-owned saved filters. `analytics_events` records dashboard and analytics activity for audit and monitoring.

Sprint 09 added permission slug:

- `analytics.manage`

## Implemented in Sprint 10

Intelligence Readiness adds normalized, explainable, reviewable intelligence infrastructure:

- `intelligence_rule_types`
- `intelligence_rules`
- `intelligence_indicators`
- `intelligence_evidence`
- `intelligence_reviews`
- `intelligence_processing_jobs`
- `intelligence_activities`

`intelligence_rules` stores deterministic rule definitions, thresholds, module scope, severity defaults, version, active state, creator/updater users, admin-managed priority, weight, execution frequency, documentation URL, description, and latest execution metadata.

`intelligence_indicators` stores generated advisory signals with source polymorphic references, module, severity, confidence score, status, detected timestamp, rule version, detection payload, and metadata. Indicators reference source facts and do not replace them. New engine-generated indicators may additionally reference `civic_intelligence_runs`; the relationship is nullable so historical indicators remain intact.

`intelligence_evidence` links indicators to supporting source records. `intelligence_reviews` stores human review decisions. `intelligence_processing_jobs` stores preparation jobs for future OCR, AI review, and search synchronization without running real OCR or LLM processing. `intelligence_activities` stores append-only timeline events.

Sprint 10 added permission slug:

- `intelligence.manage`

## Implemented in Sprint 13 Part 1

Civic Intelligence Engine run history adds a reproducibility and audit table:

- `civic_intelligence_runs`

`civic_intelligence_runs` records each deterministic engine execution with UUID, engine version, status, triggering user, started/completed timestamps, rules executed, indicators created, threshold snapshot, summary payload, and notes. Deletion is blocked at the model layer so run history remains append-only for audit review.

Engine runs do not duplicate source project, budget, procurement, contractor, document, citizen report, agency, geography, search, or analytics facts. Generated indicators continue to live in `intelligence_indicators` with source-linked evidence and human review state.

Indexes support status filtering, engine-version filtering, started/completed timeline lookup, UUID retrieval, and run-scoped indicator severity timelines. Candidate indexes support delay, approved-award concentration, and unresolved citizen-report queries.

## Implemented in Sprint 13 Part 2

Production hardening extends deterministic rule governance with an append-only audit table:

- `intelligence_rule_audits`

`intelligence_rule_audits` records rule configuration updates and executions with actor, event name, before/after snapshots, and occurrence timestamp. Administrators must change rules through `RuleManagementService` and the admin console so rule weights, thresholds, severity, priority, documentation, active state, execution frequency, and last-run metadata remain reviewable.

The new columns on `intelligence_rules` are additive and nullable/defaulted for backwards-compatible rollout. Rollback removes only the Part 2 governance columns and audit table; it does not delete existing indicators or engine run history.

## Implemented in Sprint 11

Public Transparency & Citizen Engagement adds moderated citizen reporting:

- `citizen_report_categories`
- `citizen_report_statuses`
- `citizen_reports`
- `citizen_report_activities`

Citizen reports use a public UUID for tracking instead of exposing internal IDs. Reports reference the submitter, category, status, optional project, agency, document, and geography records. Report activities preserve submitted, status change, archive, and restore events with actor, old/new values, request IP, and user agent.

Sprint 11 added permission slug:

- `citizen_reports.manage`

## Change-Request Governance

`change_requests` stores staff proposals without replacing source-domain records. It supports an operation, nullable target identifier for proposed creation, structured payload, supporting attachment metadata, requester, reviewer, review status, and review notes. `change_request_activities` is append-only and records submission, review, and source-application confirmation with the acting user, notes, and metadata. Approval and application confirmation never mutate the proposed source record automatically.

Agency records include optional `latitude`, `longitude`, and `geojson`. Organization records include optional `headquarters_latitude`, `headquarters_longitude`, and `headquarters_geojson`. These are additive presentation/location fields and do not replace the normalized geography foreign keys.

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

## V2 Experience Preferences

`user_district_preferences` stores only the district IDs explicitly selected by an authenticated user. Its composite primary key prevents duplicate selections, both foreign keys cascade safely, and the reverse `(district_id, user_id)` index supports district audiences. Precise browser coordinates, accuracy, and location history are never persisted.

`user_notification_preferences` normalizes approved civic-change categories by user while the existing `users.notification_preferences` JSON remains a compatibility projection for appearance and established notification settings. The unique `(user_id, category)` key makes updates idempotent; `(category, enabled)` supports notification fan-out. Major approved changes default on in the application contract, while users may choose a custom category set.

## V2 Expansion Notes

Introduce AI tables only when the ingestion and review workflow exists. Keep AI outputs separate from source facts. Future GIS work may add spatial indexes, map tiles, and GeoJSON validation around the Sprint 02 geography tables without replacing the normalized hierarchy.
