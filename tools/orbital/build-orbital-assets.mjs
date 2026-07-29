import { resolve } from 'node:path';

import { buildOrbitalAssets } from './orbital-surface.mjs';

const root = resolve(import.meta.dirname, '../..');
const outputDirectory = resolve(root, 'public/images/orbital');
const basePath = '/tmp/civiclens-blue-marble-august-5400.jpg';
const cloudPath = '/tmp/civiclens-clouds-only-2048.jpg';
const fallbackPath = resolve(outputDirectory, 'nasa-blue-marble-august-5400x2700.jpg');
const runtimePath = resolve(outputDirectory, 'nasa-blue-marble-clouds-2026-07-27-5400x2700.jpg');
const manifestPath = resolve(outputDirectory, 'manifest.json');

const result = await buildOrbitalAssets({
  basePath,
  cloudPath,
  fallbackPath,
  runtimePath,
  manifestPath,
});

process.stdout.write(`${JSON.stringify({
  fallbackPath,
  runtimePath,
  manifestPath,
  runtimeSha256: result.runtime.sha256,
}, null, 2)}\n`);
