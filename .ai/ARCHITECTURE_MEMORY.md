# Architecture Memory

## Architecture Style

Modular Laravel monolith with explicit domain boundaries.

## Domains

- Identity and access.
- Agencies and locations.
- Projects and status.
- Budgets and procurements.
- Suppliers and contracts.
- Documents and reports.
- Search and analytics.
- Audit logging.
- Intelligence readiness.

## Sprint 02 Foundation

Locations are implemented as a normalized country -> division -> district -> upazila -> union -> ward hierarchy. Agencies are hierarchical, typed organizations that can be assigned to geography and users. Future projects, budgets, procurement, and documents should reference these canonical tables instead of duplicating names.

## Sprint 03 Foundation

Projects are implemented as the core CivicLens business aggregate. They reference agencies, geography, lookup tables, creator/updater users, and project activity audit records. Future budgets, procurement, contractors, documents, and reports should attach to `projects.id`.

## Sprint 04 Foundation

Budgets are the single source of truth for project financial values. Projects no longer own allocation or expenditure amounts. Financial history is preserved through budget revisions and immutable budget transactions.

## V2 Guardrail

Do not merge OCR, semantic search, or anomaly models into v1 without ADR approval.
