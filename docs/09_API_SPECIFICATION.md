# 09 API Specification

## Conventions

- JSON responses.
- Plural resource names.
- Laravel validation error format.
- Sanctum bearer tokens for private APIs.
- Public read-only APIs may be introduced in v2.

## Core Endpoints

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
- `POST /admin/procurement/tenders/{tender}/awards`
- `POST /admin/procurement/awards/{award}/contracts`
- `GET /admin/procurement/contracts/{contract}`
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

## V2 Expansion Notes

Create an OpenAPI document and split public API contracts from private admin APIs.
