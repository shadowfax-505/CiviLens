# Projects Module

## Responsibility

Manage civic project records, statuses, locations, timelines, and relationships to budgets, procurements, documents, and reports.

## Implemented Features

- Project dashboard/listing with global search, project code search, agency/category/status/priority/funding/fiscal year/geography filtering, date ranges, progress ranges, sorting, pagination, and URL-persistent filters.
- Project create, detail view, edit, archive, restore, and policy-controlled delete.
- Parent-child project hierarchy.
- Normalized lookup tables for categories, statuses, priorities, funding sources, and fiscal years.
- Agency and geography assignment using Sprint 02 canonical tables.
- Timeline, progress, public visibility, active status, latitude/longitude, GeoJSON placeholder, and featured image path fields.
- Create/edit forms include an embedded geographic location section for assigning hierarchy and map coordinates.
- Project financial values are owned by the Finance module and related through budgets.
- Lifecycle audit events through `project_activities`.
- Admin/staff project authorization through the existing custom role and permission system.
- Pest coverage for CRUD, authorization, validation, search/filter/sort/pagination, relationships, soft deletes, factories, seeders, and policy enforcement.
- Sprint 11 public project explorer and detail pages expose only active, public, unarchived projects through public-safe services.
- Project detail views include a reusable Leaflet/OpenStreetMap map when coordinates or GeoJSON are present.

## V1 Tables

- `projects`
- `project_categories`
- `project_statuses`
- `project_priorities`
- `funding_sources`
- `fiscal_years`
- `project_activities`

## Relationships

- Project belongs to agency.
- Project belongs to category, status, priority, funding source, and fiscal year.
- Project may belong to country, division, district, upazila, union, and ward.
- Project may belong to a parent project.
- Project may have many child projects.
- Project belongs to creator and updater users.
- Project has many lifecycle activity records.
- Project has many budgets.

## Search Notes

Sprint 03 uses indexed database filtering as the authoritative v1 search path. `App\Services\Projects\ProjectListingService` centralizes filters so future Scout/Meilisearch indexing can reuse the same request contract with minimal controller changes. Sprint 04 moves budget amount filtering to `App\Services\Finance\BudgetListingService`.

## V2 Notes

Add public status feeds, map layers, and delay indicators.
# Change proposal governance

Only administrators may create, update, archive, restore, or delete project records. Staff retain read access and submit proposed changes through the Change Requests queue, where an administrator records the review decision.
