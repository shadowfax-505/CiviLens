# Procurement Module

## Responsibility

The Procurement & Tender Management module models the public procurement lifecycle from tender preparation through bids, evaluation, award, contract creation, and timeline auditability.

## Implemented Scope

- Procurement methods, tender categories, and tender statuses are configurable lookup tables.
- Tenders belong to projects, budgets, agencies, methods, categories, and statuses.
- Bidder organizations submit bid submissions against tenders.
- Evaluation criteria and scores support the evaluation workflow.
- Awards connect tenders to winning bid submissions.
- Contracts connect awards to projects and budgets without copying finance-owned allocation values.
- Contract lifecycle tables exist for milestones, variation orders, extensions, liquidated damages, and completion certificates.
- Procurement activities record immutable audit timeline events.

## Admin UI

Implemented routes provide:

- Procurement dashboard with active/closed tender counts, average bidders, average evaluation time, award rate, and contract status summary.
- Tender list with URL-persistent search, filtering, sorting, and pagination.
- Tender create/edit forms.
- Tender detail workspace for publish/close/archive/restore, bids, evaluation criteria, scores, awards, contracts, and activity timeline.
- Contract detail view with bidder, project, budget reference, milestones, and timeline.

## Authorization

Actions are protected with `TenderPolicy`. Administrators and users with `procurements.manage` can manage procurement records. Delete operations are intentionally unsupported for tenders and procurement activities.

## Data Integrity

Procurement references finance records instead of duplicating budget allocations or expenditures. Timeline events are append-only through `procurement_activities`, and model deletion is blocked.

## Tests

Pest coverage includes authorization, tender lifecycle, validation, search/filter/sort/pagination, bid submission, evaluation scoring, awards, contract creation, seed data, relationships, and activity immutability.

## V2 Notes

Future sprints can attach document storage, AI document review, anomaly detection, and Meilisearch indexing to the existing tender, bid, contract, and activity records without replacing the normalized source tables.
