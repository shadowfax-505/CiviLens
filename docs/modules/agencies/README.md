# Agencies Module

## Change Proposal Governance

Only administrators may create, update, or delete agency registry records. Staff can propose new agencies or corrections through the Change Requests queue; proposals can identify an existing target record or request a new one.

## Responsibility

Manage agencies or public-sector departments responsible for projects.

## Implemented Features

- Agency listing with global search, status filtering, agency type filtering, sorting, pagination, and URL-persistent filters.
- Agency create, edit, update, and soft delete.
- Hierarchical parent agency support.
- Agency type assignment.
- Optional geography assignment using the Sprint 02 normalized hierarchy.
- Contact fields: website, email, phone, address, contact person, and description.
- User assignment through `agency_user`.
- Admin-only policy protection using the existing custom role system.
- Pest feature and unit coverage for CRUD, authorization, validation, search, hierarchy, geography assignment, and user assignment.

## V1 Tables

- `agencies`
- `agency_types`
- `agency_user`

## Relationships

- Agency belongs to agency type.
- Agency may belong to a parent agency.
- Agency may have many child agencies.
- Agency may belong to country, division, district, upazila, union, and ward.
- Agency belongs to many users.

## V2 Notes

Add public agency profiles and performance dashboards.

Sprint 11 implements public agency profiles with public-safe related project and procurement summaries.
