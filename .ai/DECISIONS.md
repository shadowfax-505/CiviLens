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
| Implement contractors as normalized organization/profile intelligence records with derived service-calculated scores | Accepted | Documented in `docs/modules/contractors/README.md` |
| Implement documents as normalized metadata records with Laravel Storage files, immutable versions, and polymorphic attachments | Accepted | Documented in `docs/modules/documents/README.md` |
| Implement universal search as provider-agnostic infrastructure with `Searchable` and `SearchProvider` contracts | Accepted | `docs/adr/ADR-008-Provider-Agnostic-Universal-Search.md` |
