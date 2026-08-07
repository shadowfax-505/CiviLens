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
- Procurement owns plans, tenders, bid workflow, evaluations, awards, contracts, milestones, variations, payments, closeouts, and procurement activities.
- Search owns provider-agnostic indexing, discovery, suggestions, history, saved searches, analytics, and knowledge graph traversal.
- Analytics owns cross-module metric calculation, dashboards, snapshots, reports, alerts, and chart-ready definitions while reading source facts from operational modules.
- Intelligence owns deterministic rule execution, evidence-backed risk indicators, human review, processing readiness, and reproducible Civic Integrity Engine runs. It does not run OCR, LLMs, embeddings, vector search, semantic search, or autonomous agents in v1.

## Sprint 08 Search Infrastructure

Universal Search is implemented as platform infrastructure behind `Searchable` and `SearchProvider` contracts. Business modules expose their own search payloads and relationships; controllers call `SearchManager`, `SearchIndexingService`, or `KnowledgeGraphService` instead of provider clients.

The current provider is `DatabaseSearchProvider`. Future Scout, Meilisearch, and OpenSearch providers must remain interchangeable behind `SearchProvider`.

## Sprint 09 Analytics Infrastructure

Business Intelligence is implemented as a dedicated Analytics module. `MetricRegistry` registers metrics by key, `MetricEngine` calculates cached metric values, `AggregationEngine` centralizes source-record filters, and `DashboardService` composes dashboard payloads.

Analytics persistence is limited to immutable snapshots, generated report records, rule definitions, triggered alerts, dashboard states, and analytics events. Operational facts remain owned by projects, finance, procurement, contractors, documents, search, identity, agencies, and geography.

## Sprint 11 Public Transparency Infrastructure

Public Transparency is implemented as a public-safe web layer over existing source domains. Public controllers call dedicated services for visibility checks, listing, detail composition, search, document downloads, and citizen report workflow. Citizen report records and activities are their own moderated domain and do not replace project, procurement, document, or analytics facts.

## Sprint 12 Procurement Lifecycle Infrastructure

Enterprise Procurement extends the existing Sprint 05 source tables through services, Form Requests, policies, and workflow routes. `ProcurementPlanningService` owns plan creation and approval. `ProcurementLifecycleService` owns bid opening, evaluation finalization, award approval, contract payment recording, milestone completion, variation approval, and contract closeout. `EnterpriseProcurementAnalyticsService` calculates procurement intelligence metrics from operational records without persisting duplicate source facts.

Procurement plans implement `Searchable` and are registered in the provider-agnostic search registry. Public procurement pages expose only active public tenders and approved awards whose disclosure status is public.

## Sprint 13 Production Readiness and Civic Integrity Engine

Sprint 13 part 1 adds a production deployment scaffold and deterministic integrity analysis without changing module ownership. Docker, Nginx, PHP-FPM, Redis, MySQL, dedicated queue-worker and scheduler roles, production PHP settings, `.env.production.example`, and `/healthz` support deployment verification. The scheduler runs `civiclens:integrity-run` daily, and `/nginx-healthz` remains separate from dependency diagnostics.

`CivicIntegrityEngineService` coordinates active `IntelligenceRule` records through the existing `RuleExecutionService`. Each run stores engine version, status, threshold snapshots, rules executed, indicators created, and summary payload in `civic_intelligence_runs`. Engine-generated indicators are linked by a nullable foreign key and retain their legacy metadata tag for payload compatibility, then flow through the existing evidence, search, knowledge graph, and human review architecture. `IntelligenceCandidateQueryService` supplies stably ordered source candidates for both execution and dry-run estimates so their deterministic matching logic cannot drift. Execution is capped at 25 candidates per rule; dry-run payloads retain their full estimate and add the cap, capped count, and truncation state.

Sprint 13 part 2 hardens the same architecture with additive production services instead of parallel systems. `ExecutiveDashboardService` composes the authenticated command center from existing source modules, analytics, search, integrity runs, and `SystemMetricsService`. `SystemMetricsService` owns `/healthz`, `/version`, and authorized `/admin/system/metrics` payloads. `RuleManagementService` owns audited rule configuration updates, dry-run estimation, and execution metadata while `intelligence_rule_audits` preserves rule-change history.

Report generation remains inside the Analytics module through `ReportBuilder`, which now emits CSV, spreadsheet-compatible, and PDF payloads from stored dashboard payloads and evidence metadata. Request correlation is middleware-owned and adds `X-Request-Id` to responses and structured log context.

## V2 Expansion Notes

Add OCR workers, vector search, map services, and public API gateways behind clear interfaces.

## V2 Governed Source Acquisition

The ingestion module extends the document boundary rather than replacing it. `source_publishers` and `source_endpoints` define administrator-approved government and non-government sources. `SourceConnector` implementations discover resources through API/feed, sitemap, direct-download, static-HTML, or an isolated browser provider. `RunSourceEndpointCrawl` records a crawl run and dispatches separately rate-limited `FetchDiscoveredResourceArtifact` jobs.

`ApprovedSourceUrlGuard` requires HTTPS, exact endpoint host allowlisting, public DNS results, and standard ports. `SafeHttpTransport` pins the resolved public address when cURL is available, revalidates every redirect, disables automatic redirects, bounds response bytes, and accepts only safe conditional headers. `SecureArtifactFetcher` checks the declared and detected media types and requires malware status `clean` before an artifact can leave quarantine.

`source_artifact_versions` is the private immutable acquisition ledger. Content-addressed paths, SHA-256 uniqueness, and `supersedes_id` preserve changed publisher versions without duplicating unchanged content. Stage 4 may link an accepted artifact to an existing `document_version`; no acquisition record directly mutates projects, budgets, procurement, contractors, agencies, or public projections.

## V2 Native Extraction and Page Routing

Extraction reads native content before considering OCR. `NativeExtractorRegistry` selects an implementation of `NativeTextExtractor` by media type: `PdfNativeTextExtractor` shells out to poppler through `Symfony\Component\Process` with array arguments, no shell interpolation, and a configured timeout; `PlainTextNativeExtractor` handles text, HTML, CSV, XML, and JSON in process. `pdfinfo` supplies the authoritative page count and page geometry, and `pdftotext` emits the whole document in one call with pages separated by form feeds.

`NativeExtractionService` copies the artifact from its private disk into a short-lived `0600` temporary file, so extraction never assumes a local filesystem path and no storage path reaches a caller or a failure message. Quarantined artifacts are refused outright. Failures mark the run `failed` with a generic reason and still record `completed_at`.

`PageRoutingPolicy` decides per page whether the text layer is usable. The metric is characters per square inch rather than a raw character count, because a sparse A3 page and a dense A5 page can carry identical character counts while meaning opposite things. The threshold is configuration, not a constant — it is an operating point that has to be reported and varied rather than assumed, and `civiclens:extraction-summary` prints it alongside every result.

`ScriptClassifier` labels each page `bn`, `en`, `mixed`, or `unknown` from Unicode ranges alone. The label is deliberately independent of extraction confidence: a page's script is a property of the document, not of how well an engine read it, and group-conditional calibration later partitions on this value.

Runs and pages land in the extraction measurement spine. `ExtractionRoutingReport` derives the born-digital share, which is the denominator for every later OCR claim — the share of pages that never need OCR and, inverted, the share of compute an OCR-always pipeline spends for nothing.

## Change-Request Governance and Citizen Safety

Staff submit structured change proposals through the Change Request module; administrators remain the only users who mutate operational source records. A proposal can be reviewed and then marked as applied only after the administrator completes the normal source-record workflow. This marker writes an immutable proposal audit activity and never applies payload data automatically.

Citizen dashboard and search requests remain on the public-safe read path. The citizen dashboard contains only the citizen's own reports and aggregate public counts. Authenticated administrators and staff retain their permission-aware internal search path.

Project map editing is part of the Projects workflow at `/admin/projects/map`. Legacy Geography Project Map URLs redirect for compatibility, while Geography reference tables remain operational but are not primary navigation.

Public detail maps use the same read-only Leaflet component as workspace views. Projects use their exact coordinates and otherwise fall back to assigned geography. Agencies can store direct coordinates and GeoJSON. Contractor maps prefer public headquarters coordinates and otherwise use active branch-office coordinates.

Contractor detail maps may include multiple public-safe branch markers. Internal and public map views share the same component but receive only the locations authorized for their scope.

Portfolio map data is served by `ProjectMapQueryService` through bounded public and authorized admin JSON endpoints. The public response has an explicit property allowlist and includes only public, active, unarchived projects with complete authoritative latitude/longitude pairs. The portable latitude/longitude query path is authoritative across supported databases; a nullable, MySQL-only SRID 4326 `POINT` column is synchronized as an additive optimization, while a B-tree `(latitude, longitude, id)` index supports the portable query path.
