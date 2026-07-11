# Procurement Module

## Change Proposal Governance

Tender creation, updates, publishing, closing, archiving, and restoration are administrator actions. Staff retain visibility and submit Change Requests for reviewed, auditable proposals instead of changing tender records directly.

## Responsibility

The Procurement & Tender Management module models the public procurement lifecycle from planning through tender publication, bid submission, bid opening, evaluation, award approval, contract creation, execution, variation management, milestone acceptance, payment recording, completion, closeout, and timeline auditability.

## Implemented Scope

- Procurement methods, tender categories, and tender statuses are configurable lookup tables.
- Tenders belong to projects, budgets, agencies, methods, categories, and statuses.
- Bidder organizations submit bid submissions against tenders.
- Evaluation criteria and scores support the evaluation workflow.
- Awards connect tenders to winning bid submissions.
- Contracts connect awards to projects and budgets without copying finance-owned allocation values.
- Contract lifecycle tables exist for milestones, variation orders, extensions, liquidated damages, and completion certificates.
- Procurement activities record immutable audit timeline events.
- Sprint 12 adds procurement plans, plan approval activities, bidder-to-contractor organization links, bid opening records, bid withdrawals, compliance checklist items, immutable evaluation summaries, award approvals, contract deliverables, contract payments, contract closeouts, public disclosure status on awards, and acceptance metadata on contract milestones.

## Admin UI

Implemented routes provide:

- Procurement dashboard with active/closed tender counts, average bidders, average evaluation time, award rate, and contract status summary.
- Procurement plan list, creation, detail, and approval workflow.
- Tender list with URL-persistent search, filtering, sorting, and pagination.
- Tender create/edit forms.
- Tender detail workspace for publish/close/archive/restore, bid submission, bid opening, evaluation criteria, scoring, immutable evaluation finalization, awards, award approval, contracts, and activity timeline.
- Contract detail view with bidder, project, budget reference, milestones, payment recording, variation approval, closeout, and timeline.

## Authorization

Actions are protected with `TenderPolicy` and `ProcurementPlanPolicy`. Administrators and users with `procurements.manage` can manage procurement records. Delete operations are intentionally unsupported for tenders and procurement activities.

## Data Integrity

Procurement references finance records instead of duplicating budget allocations or expenditures. Timeline events are append-only through `procurement_activities`, procurement plan events are append-only through `procurement_plan_activities`, and model deletion is blocked where immutable history is required. Bid amount display remains private until bid opening.

## Tests

Pest coverage includes authorization, tender lifecycle, validation, search/filter/sort/pagination, bid submission, bid opening privacy, evaluation scoring, immutable evaluation summaries, award approvals, contract creation/execution, variation approval, milestone acceptance, analytics metrics, route workflows, seed data, relationships, and activity immutability.

## V2 Notes

Future sprints can attach document storage, AI document review, anomaly detection, and Meilisearch indexing to the existing tender, bid, contract, and activity records without replacing the normalized source tables.

Sprint 11 exposes public procurement summaries only for active, public, unarchived tenders. Bid details, evaluation scores, and internal committee workflows remain admin-only.

Sprint 12 exposes public-safe award notices for approved awards whose disclosure status is public. Public procurement pages may show winning contractor and contract number/status, but never private bid amounts, score sheets, committee comments, or internal evaluation history.
