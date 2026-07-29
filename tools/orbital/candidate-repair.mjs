import { createHash } from 'node:crypto';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname } from 'node:path';
import sharp from 'sharp';

const NEAR_BLACK_MAX = 12;
const MIN_COMPONENT_PIXELS = 64;
const FEATHER_RADIUS = 8;
const MIN_OUTSIDE_REPAIR_SSIM = 0.995;
const JPEG_OPTIONS = { quality: 100, chromaSubsampling: '4:4:4', progressive: false, mozjpeg: false };

function offsetFor(pixel, channels) {
  return pixel * channels;
}

function isNearBlack(data, offset) {
  return data[offset] <= NEAR_BLACK_MAX && data[offset + 1] <= NEAR_BLACK_MAX && data[offset + 2] <= NEAR_BLACK_MAX;
}

function luminance(data, offset) {
  return (0.2126 * data[offset]) + (0.7152 * data[offset + 1]) + (0.0722 * data[offset + 2]);
}

function neighbors(pixel, width, height) {
  const x = pixel % width;
  const y = Math.floor(pixel / width);
  return [[x - 1, y], [x + 1, y], [x, y - 1], [x, y + 1]]
    .filter(([nextX, nextY]) => nextX >= 0 && nextX < width && nextY >= 0 && nextY < height)
    .map(([nextX, nextY]) => (nextY * width) + nextX);
}

export function detectRepairComponents(data, { width, height, channels = 3 }) {
  const visited = Buffer.alloc(width * height);
  const mask = Buffer.alloc(width * height);
  const components = [];

  for (let pixel = 0; pixel < width * height; pixel += 1) {
    if (visited[pixel] || !isNearBlack(data, offsetFor(pixel, channels))) {
      continue;
    }
    const pixels = [];
    const queue = [pixel];
    visited[pixel] = 1;
    for (let cursor = 0; cursor < queue.length; cursor += 1) {
      const current = queue[cursor];
      pixels.push(current);
      for (const next of neighbors(current, width, height)) {
        if (!visited[next] && isNearBlack(data, offsetFor(next, channels))) {
          visited[next] = 1;
          queue.push(next);
        }
      }
    }
    if (pixels.length >= MIN_COMPONENT_PIXELS) {
      components.push(pixels);
      for (const componentPixel of pixels) {
        mask[componentPixel] = 255;
      }
    }
  }

  return {
    mask,
    components,
    componentCount: components.length,
    confirmedPixels: components.reduce((total, component) => total + component.length, 0),
  };
}

function localProfile(source, fallback, component, { width, height, channels }) {
  const membership = new Set(component);
  const sourceChannels = [0, 0, 0];
  let sourceLuminance = 0;
  let fallbackLuminance = 0;
  let count = 0;
  for (const pixel of component) {
    for (const next of neighbors(pixel, width, height)) {
      if (membership.has(next)) {
        continue;
      }
      const offset = offsetFor(next, channels);
      for (let channel = 0; channel < 3; channel += 1) {
        sourceChannels[channel] += source[offset + channel];
      }
      sourceLuminance += luminance(source, offset);
      fallbackLuminance += luminance(fallback, offset);
      count += 1;
    }
  }
  return {
    sourceChannels: sourceChannels.map((value) => (count === 0 ? 0 : value / count)),
    gain: count > 0 && fallbackLuminance > 0 ? sourceLuminance / fallbackLuminance : 1,
  };
}

function componentDistances(component, width, height) {
  const membership = new Set(component);
  const distances = new Map();
  const queue = [];
  for (const pixel of component) {
    if (neighbors(pixel, width, height).some((next) => !membership.has(next))) {
      distances.set(pixel, 1);
      queue.push(pixel);
    }
  }
  for (let cursor = 0; cursor < queue.length; cursor += 1) {
    const current = queue[cursor];
    const distance = distances.get(current);
    if (distance >= FEATHER_RADIUS) {
      continue;
    }
    const x = current % width;
    const y = Math.floor(current / width);
    for (const [offsetX, offsetY] of [[-1, -1], [0, -1], [1, -1], [-1, 0], [1, 0], [-1, 1], [0, 1], [1, 1]]) {
      const nextX = x + offsetX;
      const nextY = y + offsetY;
      const next = (nextY * width) + nextX;
      if (nextX >= 0 && nextX < width && nextY >= 0 && nextY < height && membership.has(next) && !distances.has(next)) {
        distances.set(next, distance + 1);
        queue.push(next);
      }
    }
  }
  return distances;
}

export function repairCandidatePixels(source, fallback, repair, geometry) {
  const { width, height, channels = 3 } = geometry;
  const data = Buffer.from(source);
  for (const component of repair.components) {
    const profile = localProfile(source, fallback, component, geometry);
    const distances = componentDistances(component, width, height);
    for (const pixel of component) {
      const offset = offsetFor(pixel, channels);
      const alpha = Math.min(1, (distances.get(pixel) ?? FEATHER_RADIUS) / FEATHER_RADIUS);
      for (let channel = 0; channel < 3; channel += 1) {
        const matched = Math.round(Math.max(0, Math.min(255, fallback[offset + channel] * profile.gain)));
        data[offset + channel] = Math.round((profile.sourceChannels[channel] * (1 - alpha)) + (matched * alpha));
      }
    }
  }
  return { data, featherRadius: FEATHER_RADIUS };
}

export function structuralSimilarity(expected, candidate, mask, { width, height, channels = 3 }) {
  const samples = [];
  for (let pixel = 0; pixel < width * height; pixel += 1) {
    if (mask[pixel] === 0) {
      const offset = offsetFor(pixel, channels);
      samples.push([luminance(expected, offset), luminance(candidate, offset)]);
    }
  }
  if (samples.length === 0) {
    throw new Error('cannot calculate SSIM without pixels outside repairs');
  }
  const expectedMean = samples.reduce((total, [value]) => total + value, 0) / samples.length;
  const candidateMean = samples.reduce((total, [, value]) => total + value, 0) / samples.length;
  let expectedVariance = 0;
  let candidateVariance = 0;
  let covariance = 0;
  for (const [expectedValue, candidateValue] of samples) {
    expectedVariance += (expectedValue - expectedMean) ** 2;
    candidateVariance += (candidateValue - candidateMean) ** 2;
    covariance += (expectedValue - expectedMean) * (candidateValue - candidateMean);
  }
  const divisor = samples.length;
  const c1 = (0.01 * 255) ** 2;
  const c2 = (0.03 * 255) ** 2;
  return ((2 * expectedMean * candidateMean + c1) * (2 * (covariance / divisor) + c2))
    / ((expectedMean ** 2 + candidateMean ** 2 + c1) * ((expectedVariance / divisor) + (candidateVariance / divisor) + c2));
}

export function validateCandidate({ width, height, expectedWidth, expectedHeight, outsideRepairSsim, unresolvedPixels }) {
  const valid = width === expectedWidth
    && height === expectedHeight
    && outsideRepairSsim >= MIN_OUTSIDE_REPAIR_SSIM
    && unresolvedPixels === 0;
  if (!valid) {
    throw new Error('invalid candidate');
  }
  return { valid, minimumOutsideRepairSsim: MIN_OUTSIDE_REPAIR_SSIM };
}

async function pixels(filePath, width, height) {
  return sharp(filePath).removeAlpha().resize(width, height, { fit: 'fill', kernel: sharp.kernel.lanczos3 }).raw().toBuffer({ resolveWithObject: true });
}

async function sha256(value) {
  return createHash('sha256').update(value).digest('hex');
}

export async function prepareCandidate({ sourcePath, fallbackPath, outputPath, manifestPath, width = 5400, height = 2700 }) {
  const [source, fallback] = await Promise.all([pixels(sourcePath, width, height), pixels(fallbackPath, width, height)]);
  const geometry = { width, height, channels: 3 };
  const repair = detectRepairComponents(source.data, geometry);
  const repaired = repairCandidatePixels(source.data, fallback.data, repair, geometry);
  const candidateJpeg = await sharp(repaired.data, { raw: geometry }).jpeg(JPEG_OPTIONS).toBuffer();
  const decoded = await sharp(candidateJpeg).raw().toBuffer({ resolveWithObject: true });
  let unresolvedPixels = 0;
  for (let pixel = 0; pixel < width * height; pixel += 1) {
    if (repair.mask[pixel] === 255 && isNearBlack(decoded.data, offsetFor(pixel, geometry.channels))) {
      unresolvedPixels += 1;
    }
  }
  let outsideRepairSsim;
  try {
    outsideRepairSsim = structuralSimilarity(source.data, decoded.data, repair.mask, geometry);
  } catch {
    throw new Error('invalid candidate');
  }
  const validation = validateCandidate({ width: decoded.info.width, height: decoded.info.height, expectedWidth: width, expectedHeight: height, outsideRepairSsim, unresolvedPixels });
  const manifest = {
    schemaVersion: 1,
    asset: { kind: 'non-runtime-repair-candidate', width, height, format: 'jpeg' },
    source: { sha256: await sha256(await readFile(sourcePath)) },
    fallback: { sha256: await sha256(await readFile(fallbackPath)) },
    repair: { confirmedPixels: repair.confirmedPixels, componentCount: repair.componentCount, repairPercentage: (repair.confirmedPixels / (width * height)) * 100, featherRadius: repaired.featherRadius },
    validation: { outsideRepairSsim, unresolvedPixels, ...validation },
    output: { sha256: await sha256(candidateJpeg) },
  };
  await mkdir(dirname(outputPath), { recursive: true });
  await mkdir(dirname(manifestPath), { recursive: true });
  await Promise.all([writeFile(outputPath, candidateJpeg), writeFile(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`)]);
  return { validation, manifest };
}
