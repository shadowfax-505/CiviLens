# 05 Search Architecture

## Search Goals

Users should quickly find projects, agencies, documents, suppliers, and reports with filters for location, agency, status, year, budget, and keywords.

## V1 Approach

- Use Laravel Scout with Meilisearch.
- Index public fields only.
- Keep database filters authoritative.
- Rebuild indexes through queue jobs.

## Failure Mode

If Meilisearch is unavailable, fall back to indexed database search for basic queries.

## V2 Expansion Notes

Add semantic search after OCR and text extraction are stable.

