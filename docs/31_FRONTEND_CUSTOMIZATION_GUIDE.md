# 31 Frontend Customization Guide

## Purpose

CivicLens uses Laravel Blade, Tailwind 4, and Vite as the production frontend layer. The frontend can be refined after deployment, but visual edits must not change backend contracts, route names, policies, queues, Docker asset behavior, or database schemas unless a sprint explicitly requires it.

## Safe Customization Rules

- Prefer shared Blade components and `resources/css/app.css` design tokens before editing many domain views.
- Keep form field names, HTTP methods, route names, authorization-aware navigation, and browser-test button labels intact.
- Do not add fake analytics, placeholder modules, unimplemented actions, OCR, LLMs, embeddings, semantic search, or autonomous AI workflows.
- Keep `@vite(['resources/css/app.css', 'resources/js/app.js'])` in the application shell so Docker app and Nginx images share the same immutable build manifest.
- Use the profile appearance setting for light, dark, and system modes. It is stored in `users.notification_preferences.appearance` and does not require a migration.
- Validate visual changes with browser tests at desktop and mobile widths before release.

## Design Direction

The CivicLens v1 interface should feel like a civic intelligence command center: classy, dense, transparent, audit-first, and restrained. Use deep navy, teal, gold, slate, white, and high-contrast dark surfaces. Public pages should emphasize transparency and evidence; admin pages should emphasize scanning, filtering, review state, and operational decisions.

## Deployment Notes

Docker remains the canonical production target. Vercel can be evaluated through container services. Netlify is suitable for static public previews or reverse-proxy use unless a future ADR approves a separated frontend architecture.

## Civic Earth Journey

The public About/landing page can render an exact nine-stage Earth-to-Bangladesh journey. It is additive: setting `CIVICLENS_EARTH_JOURNEY_ENABLED=false` retains the established static hero and leaves every public route, CTA, summary, and About section unchanged. There are no extra Bay-of-Bengal or Dhaka stages.

`resources/js/civic-earth/index.js` owns the isolated `initializeCivicEarth` interface. Camera data and layer timing live in `journey-config.js`; responsive collision fixes remain separate from renderer logic. Cesium 1.132 is pinned in `package-lock.json`, bundled by Vite, and copied to `public/build/cesium`. Do not add a Cesium CDN script or let the browser request raw daily MODIS tiles.

The renderer keeps this resilient layer order:

1. The validated local NASA Blue Marble texture is always the lowest Cesium imagery layer and is also the non-WebGL CSS fallback.
2. The approved local NASA MODIS observation composite is calibrated above it for orbital stages 1–3.
3. Esri regional imagery, roads, and labels progressively appear during the descent. A regional service failure leaves the two local layers available.

The public manifest at `public/images/orbital/manifest.json` records provenance, dimensions, checksums, repair state, fallback version, and validation. Required attribution remains visible in the journey. Runtime imagery is reviewed and immutable; the browser never uses a daily NASA GIBS URL.

### Candidate refresh

`npm run build:orbital-candidate -- --date YYYY-MM-DD` downloads an official EPSG:4326 NASA GIBS MODIS Terra input unless `--input` supplies a local test/review file. It fills only connected RGB ≤12 components of at least 64 pixels, uses the approved local fallback, applies an eight-pixel feather, and rejects unresolved regions or outside-repair SSIM below 0.995.

Candidates are written only to `public/images/orbital/candidates/`; they are not runtime assets. The Sunday 03:15 UTC GitHub workflow prepares the T−2 candidate, runs the same tests and validation, and opens or updates a review PR only when candidate bytes or its manifest change. It never publishes, promotes, merges, or replaces the approved default. A human must inspect stages 1–5 for swaths, seams, blank regions, color discontinuities, and geographic alignment.
