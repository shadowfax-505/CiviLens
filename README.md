# CivicLens

CivicLens is an AI-assisted Civic Intelligence Platform for collecting, validating, searching, analyzing, and visualizing public-sector project information.

This repository currently contains the **Enterprise v1.0 documentation skeleton**. Version 1 focuses on a buildable Laravel foundation: authentication, roles, agencies, projects, budgets, procurements, documents, search, dashboards, and auditability.

## Repository Map

- `.ai/` - persistent AI memory, rules, sprint state, and operating checklists.
- `docs/` - engineering handbook, architecture, database, API, security, testing, and roadmap.
- `docs/adr/` - Architecture Decision Records.
- `docs/api/` - detailed REST API resource docs.
- `docs/database/` - schema and migration planning.
- `docs/modules/` - per-domain module documentation.
- `docs/sprints/` - sprint plans for v1 delivery.
- `docs/v2/` - upgrade path from v1 to a stronger v2 platform.
- `prompts/` - reusable prompts for Codex-assisted development.

## Version Strategy

Version 1 is intentionally structured so v2 can be added without rewriting the project:

- V1 documents define stable names, boundaries, tables, and APIs.
- V2 ideas live in `docs/v2/` until promoted by ADR.
- Every major feature should link back to `docs/MASTER_INDEX.md`.
- Backward-compatible database changes are preferred.
- Breaking changes require a new ADR and migration notes.

## Start Here

1. Read [docs/ENGINEERING_DIRECTIVE.md](docs/ENGINEERING_DIRECTIVE.md).
2. Read [docs/MASTER_INDEX.md](docs/MASTER_INDEX.md).
3. Read [.ai/MASTER_MEMORY.md](.ai/MASTER_MEMORY.md) before using an AI coding assistant.
4. Use [docs/sprints/Sprint-01-Foundation.md](docs/sprints/Sprint-01-Foundation.md) as the first implementation sprint.
5. Keep [docs/v2/V2_UPGRADE_PATH.md](docs/v2/V2_UPGRADE_PATH.md) updated as v1 lessons emerge.
