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

A Procurement Plan belongs to an agency, budget, project, fiscal year, funding source, and procurement method. Plan approval is tracked through immutable procurement plan activities.

A Tender has many bid submissions, evaluation criteria, awards, and immutable procurement activities. Bid submissions belong to bidder organizations and may have opening records, withdrawals, compliance checklist items, and immutable evaluation summaries. Bidder organizations can link to Sprint 06 contractor organizations for performance, compliance, licensing, legal, blacklist, and previous-award context. Awards point to winning bid submissions and approval records. Contracts belong to awards and reference the same project and budget as the tender.

Contracts own milestones, deliverables, payments, variation orders, extensions, damages, completion certificates, and closeout records. Milestone acceptance, variation approval, payment recording, and contract closeout are auditable lifecycle transitions.

Procurement must not duplicate finance-managed allocation or expenditure values. Budget filtering and dashboard context read through `budgets.current_allocation` and related budget records.

Historical traceability is represented through `procurement_activities` and `procurement_plan_activities`, which log workflow events without deletion support. Future document management and AI review features should attach supporting files and analysis to plans, tenders, bids, contracts, variations, payments, and activities rather than replacing source procurement records.

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

## Business Intelligence & Analytics

Sprint 09 implements Analytics as a cross-module decision-support layer.

Analytics reads source facts from:

- Projects
- Budgets
- Procurement
- Contractors
- Documents
- Search
- Users
- Agencies
- Geography
- Platform queues and cache-ready infrastructure

Analytics owns:

- Metric registration
- Metric calculation
- Executive command-center composition
- Dashboard composition
- Chart-ready definitions
- Immutable snapshots
- Generated report records
- Rule-based alert definitions and triggered alerts
- Dashboard states
- Analytics audit events

Analytics must not mutate source modules or store duplicated operational records. Snapshots and reports preserve calculated point-in-time payloads for reproducibility and performance only. Sprint 13 part 2 report downloads render CSV, spreadsheet-compatible, and PDF payloads from stored report data, branding, filters, KPIs, charts, and evidence metadata.

## Intelligence Readiness

Sprint 10 implements Intelligence as a deterministic, human-reviewed readiness layer.

An Intelligence Rule belongs to an Intelligence Rule Type. Rules define module scope, category, thresholds, severity defaults, version, active state, priority, weight, execution frequency, documentation URL, description, and latest execution metadata.

An Intelligence Indicator belongs to an Intelligence Rule and optionally references a source record through a polymorphic source relation. Indicators have many evidence records, reviews, and activities.

Evidence records link to supporting source records through polymorphic references. Reviews preserve human decisions such as accepted, dismissed, in review, or needs more evidence.

Intelligence Rule Audits preserve rule-management events with actor, before/after snapshots, event name, and occurrence timestamp. Administrators must use the rule-management service and console so threshold and weight changes remain reproducible.

Processing jobs prepare future OCR, AI review, and search synchronization. They do not run real OCR, LLM calls, embeddings, semantic search, or automated legal conclusions in v1.

Intelligence indicators are registered with Universal Search and exposed through the Knowledge Graph by linking back to their source and evidence records.

## Civic Integrity Engine

Sprint 13 part 1 adds a reproducible engine run aggregate around the existing Intelligence domain.

A Civic Intelligence Run stores:

- Engine version
- Status
- Triggering user
- Started and completed timestamps
- Rules executed
- Indicators created
- Threshold snapshot
- Summary payload

The engine evaluates active rules across projects, budgets, procurement, contractors, documents, citizen reports, agencies, geography, search, analytics, and historical records by reading source tables and producing advisory indicators. It does not mutate source modules or make legal conclusions.

Current Sprint 13 rules include repeat approved-award concentration by bidder and unresolved citizen-report clustering by project. Both generate source-linked evidence for human review.

Sprint 13 part 2 adds dashboard projections for integrity timelines, rule execution history, indicator distribution, agency/contractor/project/budget/document/citizen-report/geography rankings, and performance metrics. These projections read indicators, evidence, reviews, source records, and run history; they do not create a separate risk source of truth.

## Public Transparency & Citizen Engagement

Sprint 11 implements public transparency as curated read models over existing source domains plus moderated citizen reports.

A Citizen Report belongs to:

- Citizen Report Category
- Citizen Report Status
- Submitter user
- Optional project, agency, document, and geography records

Citizen report activities form the audit timeline for submission, moderation status changes, archive, and restore actions. Public tracking uses UUIDs so internal numeric IDs are not exposed.

Public project, procurement, document, agency, contractor, and search screens must read through public-safe services and must not publish private documents, internal storage paths, confidential contractor legal records, unreviewed intelligence indicators, or private analytics payloads.
