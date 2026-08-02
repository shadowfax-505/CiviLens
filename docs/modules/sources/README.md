# Governed Source Acquisition

## Purpose

This module acquires public-interest material from explicitly approved government, nonprofit, research, watchdog, and agency publishers. It never accepts an arbitrary visitor URL and never publishes a fetched body directly.

## Boundaries

- `SourcePublisher` records attribution, source class, rights decision, and active state.
- `SourceEndpoint` records one connector, its exact HTTPS hosts and path prefixes, robots/terms access decision and review date, schedule, rate, timeout, content cap, cursor, health, and pause state.
- `SourceCrawlRun` records discovery lifecycle and cumulative results.
- `DiscoveredResource` is the canonical URL ledger for one endpoint.
- `SourceArtifactVersion` is the content-addressed private evidence version and may later link to an existing `DocumentVersion`.
- `SourceActivity` is the append-only operator and pipeline audit trail.

Operational civic tables are never updated by acquisition. Stage 4 promotes only a clean artifact into the existing document service. Stage 5 creates a separate reviewed public projection.

## Connectors

`SourceConnector::discover(SourceEndpoint, CrawlCursor): DiscoveryBatch` is implemented for API/feed, sitemap, direct download, static HTML, and a provider-neutral browser renderer. API/feed is preferred, followed by sitemap/direct download and static HTML. The browser connector fails closed until an isolated provider is bound.

Discovery parsers disable XML network access, bound input bytes, cap produced resources, resolve relative links, and retain only links that pass the endpoint URL guard. ETag and Last-Modified values are stored as crawl cursors and re-used only after control-character and length validation.

## Fetch Security

`ApprovedSourceUrlGuard` requires HTTPS, port 443, no URL credentials, exact host and path-prefix membership, no dot-segment traversal, no IP-literal host, successful DNS, and exclusively public/non-reserved addresses. `SafeHttpTransport` disables automatic redirects, revalidates each redirect, pins DNS when cURL is available, uses identity encoding, and stops oversized bodies while streaming.

`SecureArtifactFetcher` applies declared/detected MIME consistency and `MalwareScanner`. Only `clean` artifacts use the approved private path. Infected or unavailable scans stay in quarantine. Public storage disks are rejected.

## Queue and Controls

`civiclens:sources-dispatch` runs every five minutes behind `INGESTION_ENABLED`. Discovery and artifact retrieval are separate unique jobs; fetch jobs use the endpoint's per-minute rate. Retries have bounded backoff. Administrators can pause, resume, or queue endpoints at `/admin/sources`.

## Invariants

- No arbitrary user URL crawling.
- No non-HTTPS or private/link-local network target.
- No automatic redirect following.
- No unscanned artifact promotion.
- No public artifact route or storage path.
- No duplicate artifact version for an unchanged checksum.
- No deletion or evidence-field mutation of artifact versions.
- No loss of source class, attribution, rights decision, checksum, or retrieval URL.
