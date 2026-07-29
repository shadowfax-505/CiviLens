import assert from 'node:assert/strict';
import { access, mkdtemp, readFile, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import sharp from 'sharp';

import { buildOrbitalAssets } from './orbital-surface.mjs';

test('builds deterministic seamless NASA surface and cloud-rich runtime assets', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'civiclens-orbital-surface-'));
  const basePath = join(directory, 'base.jpg');
  const cloudPath = join(directory, 'clouds.jpg');
  const fallbackPath = join(directory, 'fallback.jpg');
  const runtimePath = join(directory, 'runtime.jpg');
  const manifestPath = join(directory, 'manifest.json');
  const base = Buffer.alloc(96 * 48 * 3);
  const clouds = Buffer.alloc(48 * 24 * 3);

  for (let offset = 0; offset < base.length; offset += 3) {
    base[offset] = 35;
    base[offset + 1] = 75;
    base[offset + 2] = 105;
  }
  for (let offset = 0; offset < clouds.length; offset += 3) {
    clouds[offset] = 0;
    clouds[offset + 1] = 0;
    clouds[offset + 2] = 0;
  }
  for (let y = 6; y < 18; y += 1) {
    for (let x = 12; x < 36; x += 1) {
      const offset = (y * 48 + x) * 3;
      clouds[offset] = 220;
      clouds[offset + 1] = 220;
      clouds[offset + 2] = 220;
    }
  }

  await sharp(base, { raw: { width: 96, height: 48, channels: 3 } }).jpeg({ quality: 100 }).toFile(basePath);
  await sharp(clouds, { raw: { width: 48, height: 24, channels: 3 } }).jpeg({ quality: 100 }).toFile(cloudPath);

  const first = await buildOrbitalAssets({
    basePath,
    cloudPath,
    fallbackPath,
    runtimePath,
    manifestPath,
    width: 96,
    height: 48,
  });
  const firstRuntime = await readFile(runtimePath);
  const firstManifest = await readFile(manifestPath, 'utf8');
  const runtimePixels = await sharp(runtimePath).raw().toBuffer();

  const second = await buildOrbitalAssets({
    basePath,
    cloudPath,
    fallbackPath,
    runtimePath,
    manifestPath,
    width: 96,
    height: 48,
  });
  const metadata = await sharp(runtimePath).metadata();
  const manifest = JSON.parse(await readFile(manifestPath, 'utf8'));

  assert.equal(metadata.width, 96);
  assert.equal(metadata.height, 48);
  assert.equal(manifest.asset.kind, 'seamless-nasa-blue-marble-cloud-composite');
  assert.equal(manifest.repair.percentage, 0);
  assert.equal(manifest.calibration.appliedToAsset, false);
  assert.equal(manifest.sources.clouds.layer, 'MODIS_Terra_CorrectedReflectance_TrueColor');
  assert.equal(manifest.sources.clouds.acquisitionDate, '2026-07-27');
  assert.equal(manifest.sources.clouds.sha256.length, 64);
  assert.equal(first.runtime.sha256, second.runtime.sha256);
  assert.equal(firstManifest, await readFile(manifestPath, 'utf8'));
  assert.deepEqual(firstRuntime, await readFile(runtimePath));
  assert.ok(runtimePixels[(12 * 96 + 24) * 3] > runtimePixels[0]);
  assert.equal(manifest.validation.valid, true);
});

test('fails with actionable input errors before writing a runtime asset', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'civiclens-orbital-missing-'));
  const runtimePath = join(directory, 'runtime.jpg');
  const cloudPath = join(directory, 'cloud.jpg');
  const clouds = Buffer.alloc(48 * 24 * 3, 120);

  await sharp(clouds, { raw: { width: 48, height: 24, channels: 3 } }).jpeg({ quality: 100 }).toFile(cloudPath);

  await assert.rejects(() => buildOrbitalAssets({
    basePath: join(directory, 'missing-base.jpg'),
    cloudPath,
    fallbackPath: join(directory, 'fallback.jpg'),
    runtimePath,
    manifestPath: join(directory, 'manifest.json'),
    width: 96,
    height: 48,
  }), /Missing or unreadable orbital base source/i);
  await assert.rejects(() => access(runtimePath));
});

test('leaves an existing runtime untouched when cloud validation fails', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'civiclens-orbital-sentinel-'));
  const basePath = join(directory, 'base.jpg');
  const runtimePath = join(directory, 'runtime.jpg');
  const sentinel = Buffer.from('approved-runtime-sentinel');
  const base = Buffer.alloc(96 * 48 * 3, 80);

  await sharp(base, { raw: { width: 96, height: 48, channels: 3 } }).jpeg({ quality: 100 }).toFile(basePath);
  await writeFile(runtimePath, sentinel);

  await assert.rejects(() => buildOrbitalAssets({
    basePath,
    cloudPath: join(directory, 'missing-cloud.jpg'),
    runtimePath,
    manifestPath: join(directory, 'manifest.json'),
    width: 96,
    height: 48,
  }), /Missing or unreadable orbital cloud source/i);
  assert.deepEqual(await readFile(runtimePath), sentinel);
});
