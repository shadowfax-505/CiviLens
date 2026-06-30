# ADR-003: Use Meilisearch for Full-Text Search

## Status

Superseded by `ADR-008-Provider-Agnostic-Universal-Search.md`

## Context

CivicLens needs fast search across projects, documents, agencies, and reports.

## Decision

Use Meilisearch through Laravel Scout for v1 search.

Sprint 08 supersedes this direct-provider decision. Meilisearch remains a preferred future provider, but business logic now depends on the provider-agnostic `SearchProvider` interface instead of Scout or Meilisearch directly.

## Consequences

Search becomes fast and typo-tolerant but requires another service and index synchronization.

## V2 Impact

V2 can add semantic search while keeping Meilisearch for keyword search.
