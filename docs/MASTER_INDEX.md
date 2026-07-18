# CivicLens Documentation Master Index

This index is the entry point for the CivicLens Enterprise v1.0 handbook. Read these documents in order when onboarding a developer.

## Core Handbook

- [Engineering Directive](ENGINEERING_DIRECTIVE.md) - mandatory lifecycle rules for every implementation session.
- [Project Overview](PROJECT_OVERVIEW.md) - civic mission, users, and platform summary.
- [Local Setup](SETUP.md) - local development setup and Laravel scaffolding guidance.
- [V1 Implementation Plan](IMPLEMENTATION_PLAN_V1.md) - phased build sequence for v1.
- [Project Constitution](00_PROJECT_CONSTITUTION.md) - highest-level principles and non-negotiables.
- [Project Charter](01_PROJECT_CHARTER.md) - objectives, scope, milestones, and governance.
- [Product Requirements](02_PRODUCT_REQUIREMENTS.md) - functional and non-functional requirements.
- [System Architecture](03_SYSTEM_ARCHITECTURE.md) - architecture, stack, module boundaries, and data flow.
- [Database Bible](04_DATABASE_BIBLE.md) - schema plan, normalization, indexing, and migration strategy.
- [Search Architecture](05_SEARCH_ARCHITECTURE.md) - Laravel Scout and Meilisearch strategy.
- [Intelligence Layer](06_INTELLIGENCE_LAYER.md) - deterministic civic integrity analysis, risk indicators, explainability, and future AI policy.
- [UI Design System](07_UI_DESIGN_SYSTEM.md) - user experience, components, accessibility, and responsive design.
- [Security Constitution](08_SECURITY_CONSTITUTION.md) - authentication, authorization, OWASP, privacy, and auditability.
- [API Specification](09_API_SPECIFICATION.md) - REST API conventions and endpoints.
- [Testing Strategy](10_TESTING_STRATEGY.md) - test types, coverage expectations, and CI policy.
- [Deployment Guide](11_DEPLOYMENT_GUIDE.md) - local and production deployment plan.
- [DevOps Guide](12_DEVOPS_GUIDE.md) - CI/CD, backups, logs, and operations.
- [Performance Guide](13_PERFORMANCE_GUIDE.md) - caching, indexing, profiling, and load testing.
- [Monitoring](14_MONITORING.md) - logs, request IDs, metrics, health checks, version metadata, and alerts.
- [Scalability Guide](15_SCALABILITY_GUIDE.md) - future growth strategy.
- [Coding Standards](16_CODING_STANDARDS.md) - PHP, Laravel, SQL, and frontend conventions.
- [Git Workflow](17_GIT_WORKFLOW.md) - branches, commits, PRs, and releases.
- [Data Governance](19_DATA_GOVERNANCE.md) - data quality, privacy, retention, and stewardship.
- [Open Data Policy](20_OPEN_DATA_POLICY.md) - public data release principles.
- [Accessibility](21_ACCESSIBILITY.md) - WCAG and inclusive design requirements.
- [Release Management](22_RELEASE_MANAGEMENT.md) - versioning and release process.
- [v1.0.0 Release Checklist](releases/RC1_CHECKLIST.md) - deployment, rollback, smoke test, data, queue, scheduler, health, metrics, logs, backup, restore, and known-risk checks.
- [v1.0.0 Release Notes](releases/RC1_RELEASE_NOTES.md) - stable release highlights, architecture, security, performance, testing, migration notes, limitations, and roadmap.
- [v1.0.0 Production Readiness Report](releases/RC1_PRODUCTION_READINESS_REPORT.md) - validation gates, database counts, browser coverage, benchmark notes, security status, and deployment caveats.
- [v1.0.0 Deployment Strategy](releases/RC1_DEPLOYMENT_STRATEGY.md) - provider discovery, target selection, infrastructure diagram, runtime/database/cache/queue/storage/logging/monitoring/backup/rollback/DR architecture, and deployment blocker.
- [Risk Register](23_RISK_REGISTER.md) - risks and mitigations.
- [Project Roadmap](24_PROJECT_ROADMAP.md) - phased delivery plan.
- [Contributing Guide](25_CONTRIBUTING_GUIDE.md) - contributor expectations.
- [Glossary](26_GLOSSARY.md) - domain and engineering terms.
- [FAQ](27_FAQ.md) - common questions.
- [Tech Stack](28_TECH_STACK.md) - frameworks, packages, and service choices.
- [Design Principles](29_DESIGN_PRINCIPLES.md) - durable engineering and product principles.
- [Domain Model](30_DOMAIN_MODEL.md) - business entities and relationships.
- [Frontend Customization Guide](31_FRONTEND_CUSTOMIZATION_GUIDE.md) - deploy-safe Blade, Tailwind, dark-mode, and UI customization rules.

## Supporting Areas

- [ADRs](adr/ADR-000-Template.md) - architecture decisions.
- [API Docs](api/Projects.md) - resource-specific API docs.
- [Database Docs](database/SchemaOverview.md) - schema and migration details.
- [Diagrams](diagrams/erd/CivicLens-ERD.mmd) - ERD and C4 diagrams.
- [Modules](modules/projects/README.md) - domain modules.
- [Identity Module](modules/identity/README.md) - authentication, profile, admin user management, roles, permissions, and account activity.
- [Geography Module](modules/geography/README.md) - normalized country-to-ward administrative hierarchy.
- [Agencies Module](modules/agencies/README.md) - government agency registry, hierarchy, location assignment, and user assignment.
- [Finance Module](modules/finance/README.md) - budget engine, revisions, transactions, and financial dashboard.
- [Procurement Module](modules/procurement/README.md) - procurement plans, tenders, bids, evaluation, awards, contracts, variations, milestones, payments, public notices, and immutable procurement timeline.
- [Contractor Module](modules/contractors/README.md) - contractor identity, compliance, risk, performance, and vendor intelligence.
- [Document Module](modules/documents/README.md) - enterprise document management, secure storage, versioning, metadata, permissions, and AI-ready processing hooks.
- [Search Module](modules/search/README.md) - provider-agnostic universal search, knowledge graph, suggestions, saved searches, and analytics.
- [Analytics Module](modules/analytics/README.md) - business intelligence dashboards, KPI engine, snapshots, CSV/spreadsheet/PDF reports, alerts, and chart-ready analytics.
- [Intelligence Module](modules/intelligence/README.md) - Civic Integrity Engine runs, rule management, rule-based indicators, evidence review, processing readiness, and explainability governance.
- [Public Module](modules/public/README.md) - public transparency portal, safe browsing, public search, staff change requests, and citizen report moderation.
- [Sprints](sprints/Sprint-01-Foundation.md) - delivery plans.
- [V2 Upgrade Path](v2/V2_UPGRADE_PATH.md) - controlled evolution after v1.
