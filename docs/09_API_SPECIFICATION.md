# 09 API Specification

## Conventions

- JSON responses.
- Plural resource names.
- Laravel validation error format.
- Sanctum bearer tokens for private APIs.
- Public read-only APIs may be introduced in v2.

## Planned Private JSON API Endpoints

Version 1.0 RC1 ships authenticated web routes and JSON-returning web endpoints for selected admin workflows. Dedicated `/api/*` token endpoints remain planned and require Sanctum installation before promotion:

- `POST /api/auth/register`
- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/users`
- `GET /api/agencies`
- `GET /api/projects`
- `POST /api/projects`
- `GET /api/projects/{id}`
- `PUT /api/projects/{id}`
- `DELETE /api/projects/{id}`
- `GET /api/search`
- `GET /api/analytics`

## Current Implementation Status

The Laravel app currently implements web identity routes for:

- `GET /register`
- `POST /register`
- `GET /login`
- `POST /login`
- `POST /logout`
- `GET /forgot-password`
- `POST /forgot-password`
- `GET /reset-password/{token}`
- `POST /reset-password`
- `GET /verify-email`
- `GET /verify-email/{id}/{hash}`
- `POST /email/verification-notification`
- `GET /confirm-password`
- `POST /confirm-password`
- `GET /profile`
- `PUT /profile`
- `POST /profile/avatar`
- `PUT /profile/password`
- `PUT /profile/notifications`
- `GET /admin/users`
- `PATCH /admin/users/{user}/status`
- `PATCH /admin/users/{user}/lock`
- `PUT /admin/users/{user}/roles`
- `PUT /admin/users/{user}/password`
- `GET /admin/geography/countries`
- `POST /admin/geography/countries`
- `PUT /admin/geography/countries/{country}`
- `DELETE /admin/geography/countries/{country}`
- `GET /admin/geography/divisions`
- `POST /admin/geography/divisions`
- `PUT /admin/geography/divisions/{division}`
- `DELETE /admin/geography/divisions/{division}`
- `GET /admin/geography/districts`
- `POST /admin/geography/districts`
- `PUT /admin/geography/districts/{district}`
- `DELETE /admin/geography/districts/{district}`
- `GET /admin/geography/upazilas`
- `POST /admin/geography/upazilas`
- `PUT /admin/geography/upazilas/{upazila}`
- `DELETE /admin/geography/upazilas/{upazila}`
- `GET /admin/geography/unions`
- `POST /admin/geography/unions`
- `PUT /admin/geography/unions/{union}`
- `DELETE /admin/geography/unions/{union}`
- `GET /admin/geography/wards`
- `POST /admin/geography/wards`
- `PUT /admin/geography/wards/{ward}`
- `DELETE /admin/geography/wards/{ward}`
- `GET /admin/agencies`
- `POST /admin/agencies`
- `PUT /admin/agencies/{agency}`
- `DELETE /admin/agencies/{agency}`
- `GET /admin/projects`
- `GET /admin/projects/archived`
- `POST /admin/projects`
- `GET /admin/projects/{project}`
- `PUT /admin/projects/{project}`
- `PATCH /admin/projects/{project}/archive`
- `PATCH /admin/projects/{project}/restore`
- `DELETE /admin/projects/{project}`
- `GET /admin/finance/budgets`
- `GET /admin/finance/budgets/archived`
- `POST /admin/finance/budgets`
- `GET /admin/finance/budgets/{budget}`
- `PUT /admin/finance/budgets/{budget}`
- `PATCH /admin/finance/budgets/{budget}/archive`
- `PATCH /admin/finance/budgets/{budget}/restore`
- `POST /admin/finance/budgets/{budget}/revisions`
- `POST /admin/finance/budgets/{budget}/transactions`
- `DELETE /admin/finance/budget-transactions/{budgetTransaction}` returns 405 because financial transactions are immutable.
- `GET /admin/procurement/plans`
- `POST /admin/procurement/plans`
- `GET /admin/procurement/plans/create`
- `GET /admin/procurement/plans/{plan}`
- `PATCH /admin/procurement/plans/{plan}/approve`
- `GET /admin/procurement/tenders`
- `GET /admin/procurement/tenders/archived`
- `POST /admin/procurement/tenders`
- `GET /admin/procurement/tenders/{tender}`
- `PUT /admin/procurement/tenders/{tender}`
- `PATCH /admin/procurement/tenders/{tender}/publish`
- `PATCH /admin/procurement/tenders/{tender}/close`
- `PATCH /admin/procurement/tenders/{tender}/archive`
- `PATCH /admin/procurement/tenders/{tender}/restore`
- `POST /admin/procurement/tenders/{tender}/bids`
- `POST /admin/procurement/tenders/{tender}/criteria`
- `POST /admin/procurement/bid-submissions/{bidSubmission}/scores`
- `POST /admin/procurement/bid-submissions/{bidSubmission}/open`
- `POST /admin/procurement/bid-submissions/{bidSubmission}/finalize-evaluation`
- `POST /admin/procurement/tenders/{tender}/awards`
- `PATCH /admin/procurement/awards/{award}/approve`
- `POST /admin/procurement/awards/{award}/contracts`
- `POST /admin/procurement/contracts/{contract}/payments`
- `PATCH /admin/procurement/contracts/{contract}/close`
- `GET /admin/procurement/contracts/{contract}`
- `PATCH /admin/procurement/milestones/{milestone}/complete`
- `PATCH /admin/procurement/variation-orders/{variationOrder}/approve`
- `GET /admin/contractors/organizations`
- `GET /admin/contractors/organizations/archived`
- `POST /admin/contractors/organizations`
- `GET /admin/contractors/organizations/{organization}`
- `PUT /admin/contractors/organizations/{organization}`
- `PATCH /admin/contractors/organizations/{organization}/archive`
- `PATCH /admin/contractors/organizations/{organization}/restore`
- `POST /admin/contractors/organizations/{organization}/profile`
- `GET /admin/documents`
- `GET /admin/documents/archived`
- `GET /admin/documents/create`
- `POST /admin/documents`
- `GET /admin/documents/{document}`
- `GET /admin/documents/{document}/edit`
- `PUT /admin/documents/{document}`
- `GET /admin/documents/{document}/download`
- `GET /admin/documents/{document}/preview`
- `POST /admin/documents/{document}/versions`
- `PATCH /admin/documents/{document}/archive`
- `PATCH /admin/documents/{document}/restore`
- `POST /admin/documents/bulk`
- `GET /admin/search`
- `GET /admin/search/advanced`
- `GET /admin/search/analytics`
- `GET /admin/search/results`
- `GET /admin/search/suggestions`
- `POST /admin/search/saved`
- `POST /admin/search/clicks`
- `GET /admin/search/knowledge/{module}/{id}`
- `GET /dashboard`
- `GET /admin/analytics`
- `GET /admin/analytics/metrics`
- `POST /admin/analytics/snapshots`
- `GET /admin/analytics/reports`
- `POST /admin/analytics/reports`
- `GET /admin/analytics/reports/{report}/download`
- `GET /admin/analytics/alerts`
- `GET /admin/intelligence`
- `GET /admin/intelligence/dashboard/summary`
- `POST /admin/intelligence/engine/run`
- `GET /admin/intelligence/indicators`
- `GET /admin/intelligence/indicators/{indicator}`
- `PATCH /admin/intelligence/indicators/{indicator}/review`
- `GET /admin/intelligence/rules`
- `GET /admin/intelligence/rules/{rule}`
- `PATCH /admin/intelligence/rules/{rule}`
- `POST /admin/intelligence/rules/{rule}/run`
- `GET /admin/intelligence/rules/{rule}/preview`
- `POST /admin/intelligence/rules/{rule}/dry-run`
- `GET /admin/intelligence/processing-jobs`
- `POST /admin/intelligence/processing-jobs`
- `GET /admin/system/metrics`
- `GET /public`
- `GET /public/projects`
- `GET /public/projects/{project:slug}`
- `GET /public/agencies`
- `GET /public/agencies/{agency:slug}`
- `GET /public/procurement`
- `GET /public/contractors`
- `GET /public/contractors/{organization}`
- `GET /public/documents`
- `GET /public/documents/{document}/download`
- `GET /public/search`
- `GET /public/reports/create`
- `POST /public/reports`
- `GET /public/reports/{uuid}`
- `GET /citizen/reports`
- `GET /citizen/reports/{report}`
- `GET /admin/citizen-reports`
- `GET /admin/citizen-reports/{report}`
- `PATCH /admin/citizen-reports/{report}/status`
- `PATCH /admin/citizen-reports/{report}/archive`
- `PATCH /admin/citizen-reports/{report}/restore`
- `GET /healthz`
- `GET /version`

JSON API authentication endpoints are still planned and should be implemented with Sanctum when package installation is available.

## Search Endpoint Notes

Sprint 08 search endpoints are authenticated admin web endpoints. `GET /admin/search/results` and `GET /admin/search/suggestions` return JSON using the same provider-agnostic `SearchManager` and `SearchProvider` contracts as the Blade UI.

Supported query parameters include:

- `q`
- `module`
- `status`
- `visibility`
- `sort`
- `direction`
- `page`
- `per_page`

## Analytics Endpoint Notes

Sprint 09 analytics endpoints are authenticated admin web endpoints. `GET /admin/analytics/metrics` returns JSON for a single registered metric through `MetricEngine`.

Supported query parameters include:

- `dashboard`
- `metric`
- `period`
- `format`
- `date_from`
- `date_to`

## Intelligence Endpoint Notes

Sprint 10 and Sprint 13 intelligence endpoints are authenticated admin web endpoints protected by `intelligence.manage`. Indicator filters support `q`, `severity`, `status`, and `module`.

`POST /admin/intelligence/engine/run` executes the deterministic Civic Integrity Engine against active rules, records a `civic_intelligence_runs` row, and tags generated indicators with engine metadata. It does not make legal conclusions and does not run OCR, LLMs, embeddings, vector search, semantic search, or AI agents.

`GET /admin/intelligence/rules` lists deterministic rule configuration and execution metadata. `PATCH /admin/intelligence/rules/{rule}` updates active state, priority, weight, thresholds, severity, description, documentation URL, and execution frequency through validation and audit history. `POST /admin/intelligence/rules/{rule}/dry-run` returns a deterministic estimate without creating indicators.

Processing jobs prepare future OCR, AI review, and search synchronization only. They do not run OCR engines, LLMs, embeddings, or vector search in v1.

## Health Endpoint Notes

`GET /healthz` returns public-safe application, database, cache, storage, and queue readiness checks for production monitoring. The response must not expose secrets, credentials, full environment dumps, internal storage paths, or database connection details.

`GET /version` returns deploy-safe application name, version, environment label, commit, and generated timestamp. `GET /admin/system/metrics` returns authorized operational metrics for administrators.

## Public Portal Endpoint Notes

Sprint 11 public routes are web endpoints, not versioned public APIs. Public search is limited to indexed records with `visibility=public`; public document downloads stream through application routes and do not expose internal storage paths. Citizen report submission requires authentication and is rate-limited.

## Analytics Filter Parameters

Analytics web endpoints support the following filter parameters where applicable:

- `fiscal_year_id`
- `agency_id`
- `division_id`
- `district_id`
- `contractor_id`
- `project_id`
- `budget_id`
- `funding_source_id`
- `procurement_method_id`

Analytics routes are protected by `AnalyticsPolicy`. Results must remain permission-aware and must not expose records users cannot access.

## V2 Expansion Notes

Create an OpenAPI document and split public API contracts from private admin APIs.
