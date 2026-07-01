# Known Issues

- Sanctum/token API authentication is not installed yet because package access is still deferred.
- Local Playwright browser execution requires Chromium binaries. The browser test suite is configured, but developers may need to run `npx playwright install chromium` before `npm run test:e2e`.
- PHPMD or another external maintainability package is deferred because package installation was blocked by environment approval limits during Sprint 05.5.
- Procurement is implemented; future work should add document storage, external search indexing, and deeper analytics rather than replacing the normalized source tables.
- Search indexing is still database-backed; Scout/Meilisearch wiring is deferred until external services are configured.
- API schemas are high-level and still need detailed OpenAPI coverage.
- Report exports are production-usable lightweight renderers; richer XLSX/PDF packages can be added later if package installation is approved.
- V2 intelligence features are intentionally deferred.
