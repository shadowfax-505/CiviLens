# 03 System Architecture

## Architecture Summary

CivicLens v1 is a modular Laravel application backed by MySQL, Redis, and Meilisearch. The platform is organized around domain modules: users, agencies, projects, budgets, procurements, documents, reports, search, analytics, and audit logs.

## Context Diagram

```mermaid
flowchart TD
    Citizen[Citizen] --> App[CivicLens Laravel App]
    Staff[Government Staff] --> App
    Admin[Administrator] --> App
    App --> MySQL[(MySQL)]
    App --> Redis[(Redis)]
    App --> Search[(Meilisearch)]
    App --> Storage[(File Storage)]
```

## Module Boundaries

- Projects own project lifecycle and status.
- Agencies own organization metadata.
- Procurement owns suppliers, contracts, and procurement items.
- Search owns provider-agnostic indexing, discovery, suggestions, history, saved searches, analytics, and knowledge graph traversal.
- Intelligence stays isolated until v2 features are promoted by ADR.

## Sprint 08 Search Infrastructure

Universal Search is implemented as platform infrastructure behind `Searchable` and `SearchProvider` contracts. Business modules expose their own search payloads and relationships; controllers call `SearchManager`, `SearchIndexingService`, or `KnowledgeGraphService` instead of provider clients.

The current provider is `DatabaseSearchProvider`. Future Scout, Meilisearch, and OpenSearch providers must remain interchangeable behind `SearchProvider`.

## V2 Expansion Notes

Add OCR workers, vector search, map services, and public API gateways behind clear interfaces.
