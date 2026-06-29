# ADR-003: Use Meilisearch for Full-Text Search

## Status

Accepted

## Context

CivicLens needs fast search across projects, documents, agencies, and reports.

## Decision

Use Meilisearch through Laravel Scout for v1 search.

## Consequences

Search becomes fast and typo-tolerant but requires another service and index synchronization.

## V2 Impact

V2 can add semantic search while keeping Meilisearch for keyword search.

