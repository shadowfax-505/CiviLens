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

## Contractor Intelligence

Sprint 06 implements contractors as a permanent vendor intelligence domain.

An Organization belongs to:

- Company Type
- Industry
- Optional country, division, district, upazila, union, and ward
- Creator and updater users

An Organization has one Contractor Profile. A Contractor Profile belongs to:

- Contractor Category
- Contractor Classification
- Registration Status
- Risk Level

Contractor profiles may have branch offices, directors, contact people, licenses, certifications, insurance policies, compliance records, legal cases, blacklist history, performance snapshots, and contractor activities.

Performance snapshots connect contractors to completed delivery evidence by referencing project, contract, agency, and budget records. Snapshots are immutable and should be generated from completed contracts. Contractor scores are derived by services from compliance and performance records rather than stored as source facts.

Future procurement, document, and intelligence modules should reference `contractor_profiles.id` or `organizations.id` instead of duplicating contractor names.

## Document Intelligence

Sprint 07 implements Documents as the durable enterprise record layer for CivicLens.

A Document belongs to:

- Document Type
- Document Status
- Document Visibility
- Owner user
- Uploader user
- Optional Document Category
- Creator and updater users

A Document has many versions, activities, permissions, tags, OCR metadata, and AI metadata. Document versions preserve immutable file replacement history with storage path, checksum, uploader, reason, and version number.

Documents attach to existing domains through polymorphic `documentables` records. Supported attachment targets include projects, budgets, tenders, contracts, contractor profiles, organizations, agencies, and geography records. This keeps documents reusable across modules while avoiding duplicated file metadata.

Future OCR, semantic search, summarization, classification, embeddings, thumbnails, previews, and virus scanning should consume queued document processing jobs and write processing results to metadata tables without replacing source document facts.

## Universal Search & Knowledge Graph

Sprint 08 implements Universal Search as platform infrastructure across existing domains.

Searchable source records implement `Searchable` and expose:

- Search title
- Search description
- Search keywords
- Search relationships
- Search module
- Search URL
- Search status
- Search visibility
- Search metadata

The search index references source records instead of owning source facts. Projects, budgets, tenders, contracts, contractor organizations, documents, agencies, and geography records are initially registered.

Knowledge graph traversal exposes related records through `KnowledgeGraphService`:

- Projects connect to agencies, budgets, procurement, and documents.
- Budgets connect to projects, procurement, and documents.
- Tenders connect to projects, budgets, agencies, and documents.
- Agencies connect to projects, procurement, and documents.
- Documents connect back to attached source records.

Future AI and semantic search features should consume the search index and graph services rather than bypassing module ownership boundaries.
