# Geography Module

## Responsibility

Maintain normalized administrative reference data for CivicLens projects, agencies, budgets, procurements, and future map features.

## Implemented Features

- Countries, divisions/states, districts, upazilas/cities, unions/municipalities, and wards.
- CRUD endpoints for every level remain available for reference data management, while the visible operator workflow is the project map assignment workspace.
- Search, filtering by parent where supported, sorting, pagination, and URL-persistent listing queries.
- Soft deletes on every geography table.
- Parent-scoped uniqueness to prevent duplicated names inside the same administrative boundary.
- Nullable latitude, longitude, and GeoJSON columns for GIS compatibility, with Leaflet/OpenStreetMap coordinate picking on admin geography forms.
- Project map workspace for assigning existing geographic reference data and exact coordinates to projects.
- Country listings support relationship filters by division/state, district, and upazila/city alongside text search.
- Admin-only policies using the existing custom role foundation.
- Factories, seed compatibility, feature tests, and unit relationship tests.

## Tables

- `countries`
- `divisions`
- `districts`
- `upazilas`
- `unions`
- `wards`

## Hierarchy

Country -> Division / State -> District -> Upazila / City -> Union / Municipality -> Ward.

## V2 Notes

Add GeoJSON validation, spatial indexes, public map layers, and interactive geographic search only after the core project and search modules are stable.
