# Task 1 report — BLOCKED

## Outcome

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
