# CivicLens Engineering Directive

This directive defines how CivicLens must be engineered throughout its lifecycle. It is mandatory reading before implementation work.

## Role

The active engineering agent is responsible for designing, implementing, documenting, testing, and maintaining CivicLens as production-quality software.

The repository documentation is the single source of truth. Never invent architecture that contradicts the documentation. If documentation is incomplete, extend it rather than replacing it.

## Project Overview

CivicLens is a production-grade Civic Intelligence Platform built with Laravel. It organizes, searches, analyzes, and visualizes public procurement, infrastructure, budgeting, project, and civic transparency data.

The platform is database-first. Search is a first-class feature. Artificial intelligence is an advisory layer only. AI never makes legal conclusions and only produces explainable observations.

## Required Reading Order

Before implementing anything, read these documents in order:

1. `docs/MASTER_INDEX.md`
2. `docs/PROJECT_OVERVIEW.md`
3. `docs/00_PROJECT_CONSTITUTION.md`
4. `docs/01_PROJECT_CHARTER.md`
5. `docs/02_PRODUCT_REQUIREMENTS.md`
6. `docs/03_SYSTEM_ARCHITECTURE.md`
7. `docs/04_DATABASE_BIBLE.md`
8. `docs/05_SEARCH_ARCHITECTURE.md`
9. `docs/06_INTELLIGENCE_LAYER.md`
10. `docs/07_UI_DESIGN_SYSTEM.md`
11. `docs/08_SECURITY_CONSTITUTION.md`
12. `docs/09_API_SPECIFICATION.md`

After that, read every document relevant to the feature being implemented.

## Required AI Memory

Before every implementation session, read:

1. `.ai/MASTER_MEMORY.md`
2. `.ai/PROJECT_MEMORY.md`
3. `.ai/ARCHITECTURE_MEMORY.md`
4. `.ai/CURRENT_SPRINT.md`
5. `.ai/DECISIONS.md`
6. `.ai/NEXT_STEPS.md`

These files represent CivicLens institutional knowledge and should be treated as senior architecture documentation.

## General Engineering Rules

- Prefer extending over replacing.
- Prefer composition over inheritance.
- Follow SOLID principles.
- Use clean architecture boundaries where practical.
- Use Domain Driven Design language where it clarifies the system.
- Use Repository and Service patterns where they reduce controller complexity.
- Keep controllers thin.
- Move business logic into services or actions.
- Never duplicate logic.
- Never hardcode configuration, IDs, or secrets.
- Always use environment variables for configuration.
- Follow PSR standards and Laravel best practices.
- Optimize database queries and avoid N+1 queries.
- Index searchable fields.
- Normalize tables unless documentation explicitly says otherwise.
- Generate meaningful migrations, realistic factories, and complete seeders.
- Always consider scalability and maintainability.

## Database Rules

The database is the core of CivicLens. Never implement a feature before understanding its relationships, constraints, indexes, foreign keys, and normalization boundaries.

If schema improvements are required, document them first.

## Search Rules

Every searchable module should support:

- Full-text search.
- Filtering.
- Sorting.
- Pagination.
- Advanced filtering.
- Relationship searching.
- Date filtering.
- Status filtering.
- Exportable search results.

Search performance must always be considered.

## Intelligence Rules

The platform must function correctly without AI.

If AI features exist, they must use explainable outputs and must never fabricate conclusions or classify people as corrupt. Acceptable AI outputs include risk indicators, pattern detection, document similarity, delay prediction, budget anomalies, duplicate invoices, missing documentation, and outlier detection.

## Implementation Standard

Every feature should include:

- Migration.
- Model.
- Factory.
- Seeder.
- Policy.
- Request validation.
- Controller.
- Service.
- Repository, when useful.
- Resource.
- Routes.
- Feature tests.
- Unit tests.
- Authorization tests.
- Validation tests.
- Documentation updates.

## UI Rules

- Follow `docs/07_UI_DESIGN_SYSTEM.md`.
- Prefer Livewire.
- Prefer reusable Blade components.
- Support responsive design.
- Accessibility matters.
- Dark mode support is preferred.

## Documentation Rules

- Architecture changes update architecture docs and ADRs.
- Database changes update `docs/04_DATABASE_BIBLE.md`.
- API changes update `docs/09_API_SPECIFICATION.md`.
- Decision changes create or update an ADR.

## Security Rules

- Validate input.
- Authorize every action.
- Escape output.
- Prevent SQL injection.
- Prevent XSS.
- Prevent CSRF.
- Prevent mass assignment.
- Rate-limit sensitive endpoints.
- Log security events.

## Performance Rules

- Minimize queries.
- Use eager loading.
- Use caching where appropriate.
- Optimize indexes.
- Queue long-running jobs.
- Measure before deep optimization.

## Task Workflow

For every task:

1. Understand the feature.
2. Read documentation.
3. Understand affected modules.
4. Determine database impact.
5. Determine API impact.
6. Determine UI impact.
7. Determine testing impact.
8. Implement.
9. Run tests.
10. Update documentation.

## Never

- Never generate placeholder code.
- Never leave TODOs unless explicitly requested.
- Never ignore documentation.
- Never break architecture.
- Never silently change APIs.
- Never create unnecessary complexity.

## Definition of Done

A feature is complete only when implementation is complete, tests pass, documentation is updated, architecture is preserved, the database is consistent, security is reviewed, performance is acceptable, and code follows project standards.

