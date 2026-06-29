# Procurement & Tender Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build Sprint 05: a normalized Procurement & Tender Management System with traceable tender, bid, evaluation, award, contract, and timeline records.

**Architecture:** Extend the modular Laravel monolith. Tenders attach to Projects, Budgets, and Agencies; procurement never duplicates budget allocation values owned by Finance. Workflow changes append immutable procurement activity records, while tender and contract records support archive/restore.

**Tech Stack:** Laravel 13, Blade, Tailwind CSS, custom CivicLens policies/permissions, Pest, database-backed search services prepared for future Scout/Meilisearch.

**Status:** Pending implementation. This plan is a task checklist, not a completed-work record.

---

## Tasks

### Task 1: Failing Procurement Tests

- [x] Add feature tests for authorization, tender CRUD, publish/close/archive/restore, bid submission, evaluation scoring, award, contract creation, search/filter/sort/pagination, validation, seeders, and immutable timeline behavior.
- [x] Add unit relationship tests for tender -> bid -> award -> contract and procurement activity.
- [x] Run tests and verify they fail because procurement code does not exist.

### Task 2: Schema, Models, Factories

- [x] Add normalized migrations for lookup tables, tenders, lots, bidders, bids, documents, committees, criteria, scores, awards, contracts, milestones, variations, extensions, damages, completion certificates, and procurement activities.
- [x] Add models, factories, relationships, casts, soft deletes where appropriate, and immutable activity guard.
- [x] Add project/budget/agency relationships to procurement models where needed.

### Task 3: Policies, Validation, Services, Routes

- [x] Add procurement permission and policy.
- [x] Add form requests for tender, bid, evaluation score, award, and contract operations.
- [x] Add listing/dashboard/lifecycle services.
- [x] Add admin procurement routes and controllers.

### Task 4: UI

- [x] Add procurement dashboard and tender index with filters and summaries.
- [x] Add tender create/edit/detail views with timeline, bids, evaluation workspace, awards, and contracts.
- [x] Add contract detail view.
- [x] Add navigation for authorized users.

### Task 5: Docs and Verification

- [x] Seed procurement lookup data and a baseline tender.
- [x] Update Database Bible, Domain Model, Procurement module docs, API spec, changelog, and AI memory.
- [x] Run `composer test`, `vendor/bin/pint --test`, and `npm run build`.
