import assert from 'node:assert/strict';
import { access, mkdtemp, readFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import sharp from 'sharp';

import {
  detectRepairComponents,
  prepareCandidate,
  repairCandidatePixels,
  structuralSimilarity,
  validateCandidate,
} from './candidate-repair.mjs';

const width = 48;
const height = 32;

function pixels(red, green, blue) {
  const data = Buffer.alloc(width * height * 3);
  for (let offset = 0; offset < data.length; offset += 3) {
    data[offset] = red;
    data[offset + 1] = green;
    data[offset + 2] = blue;
  }
  return data;
}

function paint(data, left, top, right, bottom, red, green, blue) {
  for (let y = top; y < bottom; y += 1) {
    for (let x = left; x < right; x += 1) {
      const offset = (y * width + x) * 3;
      data[offset] = red;
      data[offset + 1] = green;
      data[offset + 2] = blue;
    }
  }
}

test('detects only reportable RGB<=12 components and preserves dark-blue ocean', () => {
  const source = pixels(40, 60, 80);
  paint(source, 4, 4, 16, 16, 0, 0, 0);
  paint(source, 25, 4, 36, 15, 1, 4, 20);

  const result = detectRepairComponents(source, { width, height, channels: 3 });

  assert.equal(result.confirmedPixels, 144);
  assert.equal(result.componentCount, 1);
  assert.equal(result.mask[(8 * width) + 8], 255);
  assert.equal(result.mask[(8 * width) + 28], 0);
});

test('uses local color matching and an eight-pixel feather for each repaired component', () => {
  const source = pixels(40, 60, 80);
  const fallback = pixels(80, 120, 160);
  paint(source, 10, 8, 34, 28, 0, 0, 0);

  const components = detectRepairComponents(source, { width, height, channels: 3 });
  const repaired = repairCandidatePixels(source, fallback, components, { width, height, channels: 3 });
  const core = (18 * width + 22) * 3;

  assert.deepEqual([...repaired.data.subarray(core, core + 3)], [40, 60, 80]);
  assert.equal(repaired.featherRadius, 8);
});

test('calculates outside-repair SSIM and rejects invalid candidates', () => {
  const expected = pixels(40, 60, 80);
  const candidate = Buffer.from(expected);
  const mask = Buffer.alloc(width * height);
  paint(candidate, 10, 8, 34, 28, 255, 255, 255);
  for (let y = 8; y < 28; y += 1) {
    for (let x = 10; x < 34; x += 1) {
      mask[(y * width) + x] = 255;
    }
  }

  const ssim = structuralSimilarity(expected, candidate, mask, { width, height, channels: 3 });
  assert.equal(ssim, 1);
  assert.throws(() => validateCandidate({ width, height, expectedWidth: width, expectedHeight: height, outsideRepairSsim: 0.994, unresolvedPixels: 0 }), /invalid candidate/i);
});

test('writes a valid candidate manifest only after all Sharp validations pass', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'civiclens-candidate-'));
  const sourcePath = join(directory, 'source.jpg');
  const fallbackPath = join(directory, 'fallback.jpg');
  const outputPath = join(directory, 'candidate.jpg');
  const manifestPath = join(directory, 'candidate.json');
  const source = pixels(40, 60, 80);
  const fallback = pixels(80, 120, 160);
  paint(source, 10, 8, 34, 28, 0, 0, 0);

  await sharp(source, { raw: { width, height, channels: 3 } }).jpeg({ quality: 100 }).toFile(sourcePath);
  await sharp(fallback, { raw: { width, height, channels: 3 } }).jpeg({ quality: 100 }).toFile(fallbackPath);
  const result = await prepareCandidate({ sourcePath, fallbackPath, outputPath, manifestPath, width, height });
  const manifest = JSON.parse(await readFile(manifestPath, 'utf8'));

  assert.equal(result.validation.valid, true);
  assert.equal(manifest.validation.outsideRepairSsim >= 0.995, true);
  assert.equal((await sharp(outputPath).metadata()).width, width);
  assert.equal(manifest.output.sha256.length, 64);
});

test('fails closed before writes for invalid input candidates', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'civiclens-invalid-candidate-'));
  const sourcePath = join(directory, 'source.jpg');
  const fallbackPath = join(directory, 'fallback.jpg');
  const outputPath = join(directory, 'candidate.jpg');
  const manifestPath = join(directory, 'candidate.json');
  const blank = pixels(0, 0, 0);

  await sharp(blank, { raw: { width, height, channels: 3 } }).jpeg({ quality: 100 }).toFile(sourcePath);
  await sharp(blank, { raw: { width, height, channels: 3 } }).jpeg({ quality: 100 }).toFile(fallbackPath);

  await assert.rejects(() => prepareCandidate({ sourcePath, fallbackPath, outputPath, manifestPath, width, height }), /invalid candidate/i);
  await assert.rejects(() => access(outputPath));
  await assert.rejects(() => access(manifestPath));
});

test('rejects mismatched primary and fallback metadata before candidate writes', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'civiclens-mismatched-candidate-'));
  const sourcePath = join(directory, 'source.jpg');
  const fallbackPath = join(directory, 'fallback.jpg');
  const outputPath = join(directory, 'candidate.jpg');
  const source = pixels(40, 60, 80);
  const fallback = Buffer.alloc(24 * 16 * 3, 100);

  await sharp(source, { raw: { width, height, channels: 3 } }).jpeg({ quality: 100 }).toFile(sourcePath);
  await sharp(fallback, { raw: { width: 24, height: 16, channels: 3 } }).jpeg({ quality: 100 }).toFile(fallbackPath);

  await assert.rejects(() => prepareCandidate({ sourcePath, fallbackPath, outputPath, manifestPath: join(directory, 'candidate.json'), width, height }), /metadata mismatch/i);
  await assert.rejects(() => access(outputPath));
});
