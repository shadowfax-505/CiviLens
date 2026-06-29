# Contractor Intelligence Module

## Purpose

The Contractor Intelligence module stores normalized vendor identity, licensing, compliance, legal, performance, and lifecycle history for organizations participating in public projects.

This is not a simple CRUD registry. It is an intelligence-ready historical record. Source facts must remain traceable and historical records must not be silently overwritten.

## Tables

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

## Relationships

Organizations reference company type, industry, optional geography, and creator/updater users. Contractor profiles belong one-to-one to organizations and reference category, classification, registration status, and risk level. Performance snapshots reference contractor profile, project, contract, agency, and budget.

Blacklist histories, performance snapshots, and contractor activities are append-only and protected from deletion by model-level guards.

## Derived Intelligence

`ContractorScoreService` calculates overall, risk, compliance, delivery, financial, quality, experience, success-rate, delay, variance, evaluation, project-count, and award metrics from source records. Scores are not persisted as source-of-truth values.

## Admin UI

Implemented web routes:

- `GET /admin/contractors/organizations`
- `GET /admin/contractors/organizations/archived`
- `GET /admin/contractors/organizations/create`
- `POST /admin/contractors/organizations`
- `GET /admin/contractors/organizations/{organization}`
- `GET /admin/contractors/organizations/{organization}/edit`
- `PUT /admin/contractors/organizations/{organization}`
- `PATCH /admin/contractors/organizations/{organization}/archive`
- `PATCH /admin/contractors/organizations/{organization}/restore`
- `POST /admin/contractors/organizations/{organization}/profile`

Listings support global search, registration filtering, company type, industry, geography, status, risk filtering, sorting, pagination, and URL-persistent filters.

## Events And Queues

Domain events include `ContractorRegistered`, `LicenseExpiring`, `CertificationExpiring`, `ComplianceFailed`, `RiskScoreUpdated`, and `PerformanceSnapshotCreated`.

Prepared queue jobs include `RecalculateContractorRiskScore` and `NotifyExpiringContractorCredential`.

## Authorization

`OrganizationPolicy` protects every contractor organization route. Administrators and users with `contractors.manage` may manage contractor records. Destructive deletion is not exposed in the admin UI.

## Testing

Pest coverage includes seeding, CRUD, validation, authorization, search/filter/sort/pagination, derived calculations, immutable histories, events, and queue dispatch. Playwright browser coverage includes contractor organization create/search/detail smoke tests.

## V2 Notes

Future AI modules may use contractor source facts for anomaly indicators and risk explanations. AI outputs must remain separate from source contractor records and require ADR approval before becoming production workflow decisions.
