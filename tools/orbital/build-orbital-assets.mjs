import { resolve } from 'node:path';

import { buildOrbitalAssets } from './orbital-surface.mjs';

const root = resolve(import.meta.dirname, '../..');
const outputDirectory = resolve(root, 'public/images/orbital');
const basePath = resolve(outputDirectory, 'nasa-blue-marble-2004-12-5400x2700.jpg');
const cloudPath = resolve(outputDirectory, 'sources/nasa-cloud-observation-2048x1024.jpg');
const runtimePath = resolve(outputDirectory, 'nasa-blue-marble-cloud-observation-composite-5400x2700.jpg');
const manifestPath = resolve(outputDirectory, 'manifest.json');

const result = await buildOrbitalAssets({
  basePath,
  cloudPath,
  runtimePath,
  manifestPath,
});

process.stdout.write(`${JSON.stringify({
  basePath,
  runtimePath,
  manifestPath,
  runtimeSha256: result.runtime.sha256,
}, null, 2)}\n`);
