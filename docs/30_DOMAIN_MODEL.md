# 30 Domain Model

## Core Entities

- User
- Role
- Agency
- Location
- Project
- Project Status
- Budget
- Budget Item
- Procurement
- Procurement Item
- Supplier
- Contract
- Document
- Report Card
- Activity Log
- Anomaly Event

## Relationship Summary

Agencies manage projects. Projects belong to locations. Projects have budgets, procurements, contracts, documents, reports, and activity logs. Suppliers fulfill contracts and procurements. Users create records according to permissions.

## Geography Foundation

Sprint 02 implements location as a normalized administrative hierarchy:

Country -> Division / State -> District -> Upazila / County -> Union / Municipality -> Ward.

Each level stores its own canonical identity, optional code, optional latitude/longitude, and nullable GeoJSON for future GIS enrichment. Wards are included in v1 because they provide a stable lowest-level assignment point for future project, budget, procurement, and map features.

## Agency Registry

Agencies are hierarchical organizations. A ministry can own departments, departments can own regional offices, and regional offices can own local offices. Agencies belong to an agency type, may be assigned to any supported geography level, and can be associated with users through `agency_user`.

Future project, budget, procurement, and document modules should reference `agencies.id` and the normalized geography tables rather than duplicating organization or location names.

## Project Lifecycle

Sprint 03 implements Project as the core CivicLens business entity.

A Project belongs to:

- Agency
- Project Category
- Project Status
- Project Priority
- Funding Source
- Fiscal Year
- Creator
- Optional country, division, district, upazila, union, and ward

A Project may belong to a parent project and may have child projects. Future budget, procurement, contractor, document, and report modules should reference `projects.id`.

Lifecycle state is represented through normalized lookup tables and project fields:

- `project_statuses` stores workflow statuses such as planning, in progress, completed, and suspended.
- `project_priorities` stores priority levels.
- `funding_sources` stores funding classifications.
- `fiscal_years` stores fiscal date boundaries.
- `project_activities` records audit events for project creation, updates, status changes, progress changes, archive, restore, and delete actions.

## Financial Management

Sprint 04 implements the Budget Engine as the source of truth for project financial data. Projects do not duplicate allocation or expenditure amounts; they reference financial records through budgets.

A Budget belongs to:

- Project
- Fiscal Year
- Funding Source
- Budget Category
- Budget Type
- Budget Status

A Budget has many revisions and transactions. Budget revisions track approved allocation changes. Budget transactions track allocations, adjustments, expenditures, refunds, and transfers. Transactions are immutable and must never be deleted.

Future procurement, contracts, reports, and analytics should read financial totals from budgets, revisions, and transactions rather than project columns.

## Procurement & Tender Management

Sprint 05 implements procurement as the tender-to-contract lifecycle for public project delivery.

A Tender belongs to:

- Project
- Budget
- Agency
- Procurement Method
- Tender Category
- Tender Status

A Tender has many bid submissions, evaluation criteria, awards, and immutable procurement activities. Bid submissions belong to bidder organizations. Awards point to winning bid submissions. Contracts belong to awards and reference the same project and budget as the tender.

Procurement must not duplicate finance-managed allocation or expenditure values. Budget filtering and dashboard context read through `budgets.current_allocation` and related budget records.

Historical traceability is represented through `procurement_activities`, which logs workflow events without deletion support. Future document management and AI review features should attach supporting files and analysis to tenders, bids, contracts, and activities rather than replacing source procurement records.
