import assert from 'node:assert/strict';
import { mkdtemp, readFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import sharp from 'sharp';

import {
  gibsUrlForDate,
  prepareModisCandidate,
  tMinusTwoDate,
} from './prepare-modis-candidate.mjs';

const width = 48;
const height = 32;

function fixture(red, green, blue) {
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
      const offset = ((y * width) + x) * 3;
      data[offset] = red;
      data[offset + 1] = green;
      data[offset + 2] = blue;
    }
  }
}

test('derives a deterministic UTC T-2 date and official EPSG:4326 GIBS URL', () => {
  assert.equal(tMinusTwoDate(new Date('2026-07-29T00:30:00+06:00')), '2026-07-26');

  const url = new URL(gibsUrlForDate('2026-07-26', { width: 5400, height: 2700 }));

  assert.equal(url.origin, 'https://gibs.earthdata.nasa.gov');
  assert.equal(url.searchParams.get('LAYERS'), 'MODIS_Terra_CorrectedReflectance_TrueColor');
  assert.equal(url.searchParams.get('CRS'), 'EPSG:4326');
  assert.equal(url.searchParams.get('TIME'), '2026-07-26');
  assert.equal(url.searchParams.get('WIDTH'), '5400');
  assert.equal(url.searchParams.get('HEIGHT'), '2700');
});

test('builds a truthful non-default candidate from an injected local input without network access', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'civiclens-modis-cli-'));
  const sourcePath = join(directory, 'source.jpg');
  const fallbackPath = join(directory, 'fallback.jpg');
  const candidateDirectory = join(directory, 'candidates');
  const source = fixture(45, 70, 90);
  const fallback = fixture(80, 120, 150);
  paint(source, 10, 8, 34, 28, 0, 0, 0);

  await sharp(source, { raw: { width, height, channels: 3 } }).jpeg({ quality: 100 }).toFile(sourcePath);
  await sharp(fallback, { raw: { width, height, channels: 3 } }).jpeg({ quality: 100 }).toFile(fallbackPath);

  const result = await prepareModisCandidate({
    date: '2026-07-26',
    inputPath: sourcePath,
    outputDirectory: candidateDirectory,
    fallbackPath,
    width,
    height,
    downloader: async () => {
      throw new Error('network must not be used for injected inputs');
    },
  });
  const manifest = JSON.parse(await readFile(result.manifestPath, 'utf8'));

  assert.match(result.outputPath, /candidates\/modis-terra-2026-07-26-repaired-5400x2700\.jpg$/);
  assert.equal(manifest.asset.kind, 'non-runtime-repair-candidate');
  assert.equal(manifest.source.provider, 'NASA GIBS');
  assert.equal(manifest.source.layer, 'MODIS_Terra_CorrectedReflectance_TrueColor');
  assert.equal(manifest.source.acquisitionDate, '2026-07-26');
  assert.match(manifest.source.url, /^https:\/\/gibs\.earthdata\.nasa\.gov\//);
  assert.equal(manifest.review.required, true);
  assert.equal(manifest.review.publishedAutomatically, false);
  assert.equal(manifest.validation.valid, true);
});

test('downloads through an injected adapter only when no local input is supplied', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'civiclens-modis-download-'));
  const fallbackPath = join(directory, 'fallback.jpg');
  const fallback = fixture(80, 120, 150);
  let requestedUrl = '';

  await sharp(fallback, { raw: { width, height, channels: 3 } }).jpeg({ quality: 100 }).toFile(fallbackPath);

  await prepareModisCandidate({
    date: '2026-07-26',
    outputDirectory: join(directory, 'candidates'),
    fallbackPath,
    width,
    height,
    downloader: async (url, targetPath) => {
      requestedUrl = url;
      const source = fixture(45, 70, 90);
      paint(source, 10, 8, 34, 28, 0, 0, 0);
      await sharp(source, { raw: { width, height, channels: 3 } }).jpeg({ quality: 100 }).toFile(targetPath);
    },
  });

  assert.match(requestedUrl, /TIME=2026-07-26/);
});

test('rejects invalid dates before download or candidate writes', async () => {
  let downloaded = false;

  await assert.rejects(
    () => prepareModisCandidate({
      date: '2026-7-26',
      downloader: async () => {
        downloaded = true;
      },
    }),
    /YYYY-MM-DD/,
  );
  assert.equal(downloaded, false);
});
