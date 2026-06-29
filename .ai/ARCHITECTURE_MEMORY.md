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

## Sprint 05 Foundation

Procurement is implemented as a normalized tender-to-contract lifecycle. Tenders attach to projects, budgets, agencies, configurable procurement methods, categories, and statuses. Awards connect winning bids to contracts. Contracts reference the same project and budget as the tender and do not duplicate finance-owned allocation or expenditure totals. Procurement history is preserved through append-only `procurement_activities`.

## Sprint 05.5 Engineering Foundation

The developer platform uses GitHub Actions, PHPStan/Larastan, Pint, Pest, Rector dry-runs, Playwright, and local complexity metrics as the quality spine. Domain events and queued job scaffolds are available for future notifications, reporting, exports, OCR, AI processing, and search indexing without changing current synchronous business workflows.

## Sprint 06 Foundation

Contractors are implemented as normalized organization and profile records with append-only blacklist history, performance snapshots, and activity timelines. Derived contractor intelligence is calculated by services from compliance and performance source records rather than persisted as authoritative scores. Future AI modules may consume contractor facts but must keep generated risk explanations separate from source data.

## Sprint 07 Foundation

Documents are implemented as the enterprise record layer. Document metadata is normalized through configurable type, category, status, visibility, permission type, and tag tables. File bytes are stored through Laravel Storage, while `documents` and `document_versions` retain private storage references, checksums, version numbers, and uploader history.

Document attachments use polymorphic `documentables` records for projects, budgets, procurement, contracts, contractors, organizations, agencies, and geography records. OCR and AI metadata remain isolated preparation tables, and queued jobs provide hooks for future thumbnails, OCR, metadata extraction, virus scanning, indexing, and AI processing.

## V2 Guardrail

Do not merge OCR, semantic search, or anomaly models into v1 without ADR approval.
