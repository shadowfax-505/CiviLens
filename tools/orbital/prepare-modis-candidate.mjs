import { access, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { basename, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { prepareCandidate } from './candidate-repair.mjs';

const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/;
const DEFAULT_WIDTH = 5400;
const DEFAULT_HEIGHT = 2700;
const GIBS_LAYER = 'MODIS_Terra_CorrectedReflectance_TrueColor';

export function tMinusTwoDate(reference = new Date()) {
  const date = new Date(reference);
  date.setUTCDate(date.getUTCDate() - 2);

  return date.toISOString().slice(0, 10);
}

function validateDate(date) {
  const parsed = new Date(`${date}T00:00:00Z`);
  if (
    !DATE_PATTERN.test(date)
    || Number.isNaN(parsed.getTime())
    || parsed.toISOString().slice(0, 10) !== date
  ) {
    throw new Error('candidate date must use a valid YYYY-MM-DD value');
  }
}

export function gibsUrlForDate(date, { width = DEFAULT_WIDTH, height = DEFAULT_HEIGHT } = {}) {
  validateDate(date);
  const url = new URL('https://gibs.earthdata.nasa.gov/wms/epsg4326/best/wms.cgi');
  url.search = new URLSearchParams({
    SERVICE: 'WMS',
    REQUEST: 'GetMap',
    VERSION: '1.3.0',
    LAYERS: GIBS_LAYER,
    STYLES: '',
    FORMAT: 'image/jpeg',
    TRANSPARENT: 'false',
    TIME: date,
    CRS: 'EPSG:4326',
    BBOX: '-90,-180,90,180',
    WIDTH: String(width),
    HEIGHT: String(height),
  }).toString();

  return url.toString();
}

async function download(url, targetPath) {
  const response = await fetch(url, {
    headers: {
      Accept: 'image/jpeg',
      'User-Agent': 'CivicLens orbital candidate review workflow',
    },
    redirect: 'follow',
    signal: AbortSignal.timeout(120_000),
  });
  if (!response.ok) {
    throw new Error(`NASA GIBS download failed with HTTP ${response.status}`);
  }

  const contentType = response.headers.get('content-type') ?? '';
  if (!contentType.toLowerCase().startsWith('image/')) {
    throw new Error(`NASA GIBS returned unexpected content type: ${contentType || 'missing'}`);
  }

  await writeFile(targetPath, Buffer.from(await response.arrayBuffer()), { flag: 'wx' });
}

export async function prepareModisCandidate({
  date = tMinusTwoDate(),
  inputPath,
  outputDirectory = resolve('public/images/orbital/candidates'),
  fallbackPath = resolve('public/images/orbital/nasa-blue-marble-2004-12-5400x2700.jpg'),
  width = DEFAULT_WIDTH,
  height = DEFAULT_HEIGHT,
  downloader = download,
} = {}) {
  validateDate(date);
  const sourceUrl = inputPath ? null : gibsUrlForDate(date, { width, height });
  const temporaryDirectory = inputPath ? null : await mkdtemp(join(tmpdir(), 'civiclens-modis-'));
  const resolvedInput = inputPath ? resolve(inputPath) : join(temporaryDirectory, `modis-terra-${date}.jpg`);
  const outputPath = join(resolve(outputDirectory), `modis-terra-${date}-repaired-${width}x${height}.jpg`);
  const manifestPath = join(resolve(outputDirectory), `modis-terra-${date}-manifest.json`);

  try {
    if (inputPath) {
      await access(resolvedInput);
    } else {
      await downloader(sourceUrl, resolvedInput);
    }

    const result = await prepareCandidate({
      sourcePath: resolvedInput,
      fallbackPath: resolve(fallbackPath),
      outputPath,
      manifestPath,
      width,
      height,
      sourceDetails: inputPath
        ? {
            provider: 'local-injected-input',
            inputPath: basename(resolvedInput),
            claimedAcquisitionDate: date,
          }
        : {
            provider: 'NASA GIBS',
            product: 'MODIS Terra corrected reflectance true color',
            layer: GIBS_LAYER,
            acquisitionDate: date,
            url: sourceUrl,
            projection: 'EPSG:4326',
          },
      reviewMetadata: {
        candidateDirectory: 'public/images/orbital/candidates',
        instructions: 'Review stages 1-5 for swaths, seams, blank regions, and color discontinuities before any explicit promotion.',
      },
    });

    return { ...result, outputPath, manifestPath, sourceUrl };
  } finally {
    if (temporaryDirectory) {
      await rm(temporaryDirectory, { recursive: true, force: true });
    }
  }
}

function parseArguments(argv) {
  const options = {};
  for (let index = 0; index < argv.length; index += 1) {
    const argument = argv[index];
    const value = argv[index + 1];
    if (argument === '--date' || argument === '--input' || argument === '--output-directory' || argument === '--fallback') {
      if (!value || value.startsWith('--')) {
        throw new Error(`missing value for ${argument}`);
      }
      options[{
        '--date': 'date',
        '--input': 'inputPath',
        '--output-directory': 'outputDirectory',
        '--fallback': 'fallbackPath',
      }[argument]] = value;
      index += 1;
    } else {
      throw new Error(`unknown argument: ${argument}`);
    }
  }

  return options;
}

const isMain = process.argv[1] && basename(process.argv[1]) === basename(fileURLToPath(import.meta.url));
if (isMain) {
  try {
    const result = await prepareModisCandidate(parseArguments(process.argv.slice(2)));
    process.stdout.write(`${JSON.stringify({
      outputPath: result.outputPath,
      manifestPath: result.manifestPath,
      sourceUrl: result.sourceUrl,
      validation: result.validation,
    }, null, 2)}\n`);
  } catch (error) {
    process.stderr.write(`${error instanceof Error ? error.message : String(error)}\n`);
    process.exitCode = 1;
  }
}
