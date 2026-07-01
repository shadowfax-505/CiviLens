# CivicLens Agent Operating Manual

## Mission

CivicLens is a production-grade civic intelligence platform for public projects, budgets, procurement, contractors, documents, search, analytics, and transparency workflows. The system must help administrators and staff understand civic delivery through normalized, auditable, permission-aware data.

## Engineering Philosophy

- Preserve existing architecture before adding new capability.
- Extend modules through documented interfaces instead of rewriting completed work.
- Keep source-of-truth data in operational domain tables.
- Store derived analytics only when needed for reproducibility, performance, reporting, or audit history.
- Treat documentation and AI memory as part of the product.
- Ship production-quality code unless a file is explicitly marked experimental.

## Repository Architecture

CivicLens is a modular Laravel monolith. Domain boundaries are organized around identity, geography, agencies, projects, finance, procurement, contractors, documents, search, analytics, and platform operations.

Business controllers must stay thin. Validation belongs in Form Requests. Authorization belongs in Policies. Business logic belongs in services, actions, query builders, or domain-specific support classes. Long-running work should be prepared for queues.

## Current Module Inventory

- Identity and Access Management
- Geographic Hierarchy
- Government Agency Registry
- Project Lifecycle Management
- Financial Management and Budget Engine
- Procurement and Tender Management
- Contractor Intelligence and Vendor Management
- Enterprise Document Management
- Universal Search and Knowledge Discovery
- Business Intelligence and Analytics
- Intelligence Readiness and Evidence Review

## Coding Standards

- Follow Laravel conventions, PSR standards, and the existing repository style.
- Prefer typed properties, typed return values, and explicit value objects where useful.
- Avoid duplicated logic.
- Avoid hardcoded IDs, role IDs, permission IDs, or secrets.
- Use configuration for environment-specific behavior.
- Preserve existing user changes in the working tree.
- Do not add code comments unless they are required to explain non-obvious behavior.

## Database Design Principles

- Normalize source-of-truth operational data.
- Use foreign keys for relationships.
- Index foreign keys and frequent filters.
- Avoid duplicating financial, project, procurement, contractor, document, or search source facts.
- Use immutable records for historical events, snapshots, versions, transactions, and audit timelines.
- Document schema changes in `docs/04_DATABASE_BIBLE.md`.

## Git Workflow

- Use short-lived feature branches when branch work is requested.
- Do not amend commits unless explicitly requested.
- Do not revert user changes without explicit approval.
- Prefer non-interactive Git commands.
- Suggested commit messages should use clear conventional prefixes such as `feat:`, `fix:`, `docs:`, `test:`, or `chore:`.

## Testing And Quality Gates

Every sprint should include appropriate Pest feature and unit tests. Browser tests should be added for UI-critical workflows when Playwright is available.

Required gates for full sprint completion:

- `composer validate --strict`
- `php artisan test`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse`
- `vendor/bin/rector process --dry-run`
- `npm run build`
- `npm run test:e2e`
- `git diff --check`
- `php artisan config:cache`
- `php artisan route:cache`
- `php artisan view:cache`

If a gate cannot run locally, record the reason clearly.

## Documentation Synchronization

Update documentation whenever behavior, schema, APIs, architecture, or sprint state changes.

Common files:

- `docs/MASTER_INDEX.md`
- `docs/03_SYSTEM_ARCHITECTURE.md`
- `docs/04_DATABASE_BIBLE.md`
- `docs/09_API_SPECIFICATION.md`
- `docs/13_PERFORMANCE_GUIDE.md`
- `docs/14_MONITORING.md`
- `docs/30_DOMAIN_MODEL.md`
- `docs/modules/*/README.md`
- `.ai/*`
- `CHANGELOG.md`

## Definition Of Done

A sprint is complete only when implementation is complete, tests pass where the environment allows, documentation is synchronized, architecture is preserved, security and authorization are enforced, performance is considered, and remaining technical debt is documented.

## Security Rules

- Validate all input.
- Authorize every route and controller action.
- Escape output in Blade.
- Prevent mass assignment with guarded or fillable model definitions.
- Do not expose internal storage paths.
- Do not leak records through search, analytics, reports, or dashboards.
- Audit sensitive actions where appropriate.

## Performance Guidelines

- Avoid N+1 queries.
- Use eager loading for relationship-heavy screens.
- Cache expensive aggregates only with clear invalidation rules.
- Queue slow work such as exports, reports, search indexing, OCR, AI processing, and notifications.
- Design query services so future provider swaps do not rewrite business modules.

## AI Development Rules

- The CivicLens documentation is the source of truth.
- AI-generated code must be production-quality unless explicitly experimental.
- AI may assist with risk indicators and pattern detection but must not make legal accusations.
- Future AI outputs must be explainable, source-backed, reviewable, and separated from source facts.
- Preserve architectural consistency across sprints.

## ADR Policy

Create or update an ADR when a durable architectural decision changes. Do not create ADRs for routine feature implementation that follows the existing architecture.

Current accepted architectural guardrails include Laravel, modular monolith boundaries, custom roles/permissions, provider-agnostic search, normalized operational data, and v2 separation for advanced AI.

## MCP Usage Policy

- Use Figma MCP for UI/UX design and component generation when available.
- Use Playwright MCP for browser automation and E2E verification.
- Use Context7 MCP for framework and library documentation.
- Use Sequential Thinking MCP for complex planning and architectural reasoning.
- Use Cavemem MCP for project memory and long-term context when available.
- If an MCP is unavailable or blocked, continue implementation when safe and clearly report which MCP was unavailable and why.

## Architectural Consistency

Future agents must add features by extending existing contracts, services, policies, routes, tests, and documentation. Do not create parallel systems for existing domains. When in doubt, read the module documentation and AI memory before coding.
