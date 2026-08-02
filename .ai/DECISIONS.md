# Decisions

| Decision | Status | ADR |
| --- | --- | --- |
| Use Laravel for v1 application | Accepted | `docs/adr/ADR-001-Laravel.md` |
| Use MySQL as primary database | Accepted | `docs/adr/ADR-002-MySQL.md` |
| Keep advanced AI in v2 backlog | Accepted | `docs/adr/ADR-006-V2-Separation.md` |
| Use custom role and permission foundation for Sprint 1 | Accepted | `docs/adr/ADR-007-Custom-Role-Permission-Foundation.md` |
| Implement locations as explicit normalized administrative tables through ward level | Accepted | Documented in `docs/modules/geography/README.md` |
| Implement agencies as hierarchical typed organizations assigned to geography and users | Accepted | Documented in `docs/modules/agencies/README.md` |
| Implement projects as normalized lifecycle records with lookup tables and activity audit log | Accepted | Documented in `docs/modules/projects/README.md` |
| Make budgets the single source of truth for project financial values | Accepted | Documented in `docs/modules/finance/README.md` |
| Implement procurement as a normalized tender-to-contract lifecycle with immutable activities and budget references | Accepted | Documented in `docs/modules/procurement/README.md` |
| Extend procurement into an enterprise plan-to-closeout workflow through additive tables, service-owned transitions, public-safe award notices, and provider-agnostic search registration | Accepted | Documented in `docs/modules/procurement/README.md` |
| Implement contractors as normalized organization/profile intelligence records with derived service-calculated scores | Accepted | Documented in `docs/modules/contractors/README.md` |
| Implement documents as normalized metadata records with Laravel Storage files, immutable versions, and polymorphic attachments | Accepted | Documented in `docs/modules/documents/README.md` |
| Implement universal search as provider-agnostic infrastructure with `Searchable` and `SearchProvider` contracts | Accepted | `docs/adr/ADR-008-Provider-Agnostic-Universal-Search.md` |
| Implement analytics as a service-owned BI layer with immutable snapshots, generated reports, rule-based alerts, dashboard states, and analytics events | Accepted | Documented in `docs/modules/analytics/README.md` |
| Implement intelligence readiness as deterministic rules, advisory indicators, evidence, human reviews, and processing preparation without real AI execution | Accepted | Documented in `docs/modules/intelligence/README.md` |
| Implement public transparency as curated public web views with authenticated, moderated citizen reports | Accepted | Documented in `docs/modules/public/README.md` |
| Implement Sprint 13 part 1 production readiness and deterministic Civic Integrity Engine without OCR, LLMs, embeddings, vector search, semantic search, AI agents, or legal conclusions | Accepted | Documented in `docs/06_INTELLIGENCE_LAYER.md` and `docs/modules/intelligence/README.md` |
| Complete Sprint 13 part 2 by hardening existing dashboards, reporting, monitoring, request correlation, and audited rule management without introducing v2 AI infrastructure | Accepted | Documented in `docs/03_SYSTEM_ARCHITECTURE.md`, `docs/14_MONITORING.md`, and module docs |
| Publish v2 interpretations only through blind review, subject response where applicable, and versioned public projections | Accepted | `docs/adr/ADR-010-V2-Reviewed-Publication.md` |
| Run multilingual extraction as isolated, versioned, CPU-capable queue work with an abstention path | Accepted | `docs/adr/ADR-011-V2-Document-Intelligence.md` |
| Use browser location ephemerally and store only selected district IDs | Accepted | `docs/adr/ADR-012-V2-Geospatial-Privacy.md` |
| Include government and non-governmental public sources with explicit, separate provenance | Accepted | `docs/adr/ADR-013-V2-Heterogeneous-Public-Sources.md` |
| Use separate public, citizen, staff, and administrator shells over shared design and authorization foundations | Accepted | `docs/adr/ADR-014-V2-Role-Specific-Experience-Shells.md` |
