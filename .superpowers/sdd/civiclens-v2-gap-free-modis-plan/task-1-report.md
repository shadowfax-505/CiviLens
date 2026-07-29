# Task 1 report — FINAL APPROVED

## Final status

Task 1 is approved. The published runtime is the deterministic, visually accepted seamless NASA Blue Marble plus 2026-07-27 GIBS/MODIS observation-derived cloud composite. The daily swath repair workflow is candidate-only, validated, and cannot publish to the default runtime path.

## Initial rejected attempt (historical)

The deterministic Node/Sharp pipeline and its assets were built, but the candidate is **not approved and must not be committed**. Direct visual inspection of the generated 5400×2700 runtime texture still shows repeating triangular orbital swaths/seams after the single bounded multi-source fallback rebuild required by the task owner.

## Task-owned changes

- `tools/orbital/imagery-repair.mjs` — exact RGB `<= 12` / connected-component `>= 64` detector, ordered per-pixel fallback selection, color matching, 8-pixel feather, SSIM and fail-closed validation.
- `tools/orbital/build-orbital-assets.mjs` — build entry point.
- `tools/orbital/imagery-repair.test.mjs` — real Sharp synthetic fixture and deterministic unit coverage.
- `package.json` / `package-lock.json` — Sharp and `test:orbital` / `build:orbital` scripts.
- `public/images/orbital/*` — generated diagnostic candidate and manifest; these remain unapproved because of the visual defect.

## TDD evidence

The following RED states were observed before their corresponding implementation:

- `node --test tools/orbital/imagery-repair.test.mjs` — missing `imagery-repair.mjs` module.
- `node --test tools/orbital/imagery-repair.test.mjs` — missing `repairOrbitalImagery` export.
- `node --test tools/orbital/imagery-repair.test.mjs` — missing approved v10 calibration export.
- `npm run test:orbital` — missing ordered `fillGapChain` export.

Final test command:

```text
npm run test:orbital
7 passing, 0 failing
```

## Bounded rebuild and metrics

The ordered chain used:

1. Terra 2026-07-27 (primary, valid pixels preserved)
2. Aqua 2026-07-27 (downloaded from NASA GIBS)
3. Terra 2026-07-26
4. Terra 2026-07-25
5. Local Blue Marble residual fallback

`npm run build:orbital` completed with:

- output dimensions: `5400 × 2700`
- confirmed primary gap pixels: `3,628,732` across `21` components
- source usage: `[546,907, 244,980, 14,167, 2,822,678]`
- unresolved primary pixels: `0`
- feather radius: `8 px`
- outside-mask SSIM: `0.99998537` (minimum `0.999`)
- reportable output no-data status: `false`

## Blocking validation

The resulting repaired image was inspected directly. It has no raw black no-data swaths, but it still visibly exposes a repeating pattern of triangular multi-date seam regions across the globe. This violates the required gap-free, production-ready visual result. The numeric pipeline validation therefore cannot be treated as sufficient approval.

No commit was created and no files were staged.

---

## Fix round 1 — accepted seamless composite

The rejected daily MODIS-chain runtime path was removed from `public/images/orbital/` and replaced with a deterministic, swath-free NASA composite:

- Surface: `/tmp/civiclens-blue-marble-august-5400.jpg`
- Clouds: `/tmp/civiclens-clouds-only-2048.jpg`
- Composition: resized equirectangular cloud texture, `screen` blend at `0.68` opacity over the local Blue Marble surface.

This preserves the v10 local fallback/calibration contract without displaying any polar-orbit swath geometry. The runtime asset is a seamless, cloud-rich equirectangular surface rather than a repaired daily MODIS mosaic.

### RED/GREEN evidence

RED:

```text
node --test tools/orbital/orbital-surface.test.mjs
ERR_MODULE_NOT_FOUND: tools/orbital/orbital-surface.mjs
```

GREEN and final rerun:

```text
npm run test:orbital
1 passing, 0 failing

npm run build:orbital
completed successfully
```

### Approved runtime files

- `tools/orbital/orbital-surface.mjs`
- `tools/orbital/orbital-surface.test.mjs`
- `tools/orbital/build-orbital-assets.mjs`
- `public/images/orbital/nasa-blue-marble-august-5400x2700.jpg`
- `public/images/orbital/nasa-blue-marble-clouds-2026-07-27-5400x2700.jpg`
- `public/images/orbital/manifest.json`
- `package.json` and `package-lock.json`

The rejected `modis-terra-2026-07-27-repaired-5400x2700.jpg` and prior Blue-Marble-only runtime asset are no longer present under `public/images/orbital/`. The failed daily-chain implementation/tests are not part of the runtime path.

### Final validation

- Fallback and runtime decode as JPEG at `5400 × 2700`.
- Fallback SHA-256: `68a72fc37223b7dd50a78e2a2b0e4302deff8a93711d8206c64cfd65b68952c7`.
- Runtime SHA-256: `7e58ebc4081661ac56208323e64b05689bcd2dc63642e5dd69f4ce0d84818441`.
- Manifest records source hashes, asset hashes, calibration, dimensions, decode validation, and deterministic status.
- A second build produced the same runtime hash.
- Direct visual inspection found continuous global surface and cloud structure with no triangular polar-orbit swaths or seams.
- `git diff --check` passed before the code/assets commit.

### Commits

- `7804ab1 feat: build seamless orbital imagery assets`
- This report is committed separately as Task 1 validation documentation.

---

## Fix round 2 — candidate repair separation and truthful provenance

The approved runtime remains the visually accepted seamless NASA Blue Marble plus cloud-observation composite. No daily MODIS swath asset is published under `public/images/orbital/`.

### Candidate-only repair path

`tools/orbital/candidate-repair.mjs` now provides a separate, non-runtime candidate-preparation pipeline. It is not called by `build:orbital` and cannot replace the approved composite by default.

- Detects only RGB `<= 12` connected components of at least `64` pixels.
- Leaves dark-blue ocean pixels untouched.
- Repairs only confirmed components using an aligned fallback.
- Uses each component's local boundary profile for color matching and an 8-pixel feather.
- Calculates SSIM outside repair masks and requires `>= 0.995`.
- Validates dimensions and unresolved repaired pixels before writing either the candidate image or its manifest.
- The real Sharp synthetic suite covers detector/ocean handling, local color matching, feather configuration, SSIM, manifest output, and fail-closed no-write behavior.

### RED/GREEN evidence

RED:

```text
node --test tools/orbital/candidate-repair.test.mjs
ERR_MODULE_NOT_FOUND: tools/orbital/candidate-repair.mjs

node --test tools/orbital/orbital-surface.test.mjs
expected actionable missing-source error; received Sharp's raw input-file error
```

GREEN and final rerun:

```text
npm run test:orbital
7 passing, 0 failing

npm run build:orbital
completed from repository-controlled sources
```

### Reproducible sources and provenance

- Local fallback renamed to `nasa-blue-marble-2004-12-5400x2700.jpg`. The v10 reference URL contains `world.200412`, so the source period is correctly recorded as December 2004 rather than August.
- The small cloud input is now versioned at `public/images/orbital/sources/nasa-cloud-observation-2048x1024.jpg`.
- `build:orbital` uses the local fallback and this committed cloud source; missing inputs fail with actionable errors before a runtime asset is written.
- The manifest records source identity, URL (or explicit `null` when no source URL was supplied), acquisition period, source dimensions, SHA-256 checksums, fallback version, output checksum, and repair percentage `0`.
- The exact v10 brightness/contrast/saturation/gamma/alpha values remain in the manifest, explicitly marked `appliedToAsset: false`; Cesium applies that calibration in Task 2, so it is not destructively baked into Task 1 imagery.

### Final validation

- Runtime and fallback decode as JPEG at `5400 × 2700`.
- Candidate repair suite: 5 tests passing.
- Seamless-surface suite: 2 tests passing.
- Two consecutive `npm run build:orbital` runs produced the same runtime SHA-256: `680bf4c373620a14a8f6608b602f9b303521264674d626ef94ee37ef2f7e7bce`.
- `git diff --check` passed before the code/assets commit.
- The approved runtime was visually rechecked; it remains gap-free and has no triangular polar-orbit swaths.

### Commit

- `d7ac4a9 feat: add validated orbital candidate pipeline`

---

## Fix round 3 — final reviewer validation gates

### Candidate metadata contract

The non-runtime candidate pipeline now decodes metadata for both the primary and fallback before processing. It rejects differing decoded dimensions, an aspect mismatch, or dimensions that differ from the requested candidate contract. It no longer silently resizes either candidate input. The mismatch test also verifies that no candidate output is written.

### Atomic approved-runtime publishing

The approved runtime builder now:

1. Reads and validates metadata for both repository-controlled inputs before writes.
2. Decodes both inputs before writes.
3. Composes the runtime in memory and validates its dimensions.
4. Writes a sibling temporary runtime, decodes and validates that temporary file, then atomically renames it into place.
5. Leaves an existing runtime sentinel untouched when cloud validation fails.

### Complete cloud provenance

The manifest identifies the committed cloud input as `MODIS_Terra_CorrectedReflectance_TrueColor` from the NASA GIBS EPSG:4326 WMS endpoint, records the WMS request template, acquisition date `2026-07-27`, and processing as `observation-derived cloud extraction and screen composite`. This describes the cloud layer only; the surface remains the December 2004 Blue Marble fallback and is not claimed to be daily observation imagery.

### RED/GREEN evidence

RED:

```text
node --test tools/orbital/candidate-repair.test.mjs
rejects mismatched primary and fallback metadata before candidate writes
Missing expected rejection.
```

GREEN:

```text
npm run test:orbital
9 passing, 0 failing
```

### Final validation

- `npm run build:orbital` completed twice from committed sources.
- Both runtime SHA-256 values: `680bf4c373620a14a8f6608b602f9b303521264674d626ef94ee37ef2f7e7bce`.
- Fallback and runtime decode as `5400 × 2700` JPEG images.
- Candidate suite: 6 passing tests, including mismatch rejection and fail-closed writes.
- Runtime suite: 3 passing tests, including actionable missing inputs and runtime sentinel preservation.
- `git diff --check` passed before the code/assets commit.
- The report's initial BLOCKED attempt remains above as history; the top-level report status is final approved.

### Commit

- `d75b9cf fix: enforce orbital asset validation gates`
