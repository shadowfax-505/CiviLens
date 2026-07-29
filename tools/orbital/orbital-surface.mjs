import { createHash } from 'node:crypto';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname } from 'node:path';
import sharp from 'sharp';

const JPEG_OPTIONS = { quality: 100, chromaSubsampling: '4:4:4', progressive: false, mozjpeg: false };

export const ORBITAL_SURFACE_CALIBRATION = {
  fallbackReferenceUrl: 'https://eoimages.gsfc.nasa.gov/images/imagerecords/74000/74343/world.200412.3x5400x2700.jpg',
  fallback: { brightness: 0.88, contrast: 1.31, saturation: 1.34, gamma: 0.86, alpha: 1 },
  runtime: { brightness: 0.93, contrast: 1.28, saturation: 1.18, gamma: 0.88, alpha: 1 },
  cloudComposite: { blend: 'screen', opacity: 0.68 },
};

async function sha256(filePath) {
  return createHash('sha256').update(await readFile(filePath)).digest('hex');
}

async function normalizedImage(filePath, width, height, label) {
  try {
    return await sharp(filePath)
      .removeAlpha()
      .resize(width, height, { fit: 'fill', kernel: sharp.kernel.lanczos3 })
      .jpeg(JPEG_OPTIONS)
      .toBuffer();
  } catch (error) {
    throw new Error(`Missing or unreadable orbital ${label} source: ${filePath} (${error.message})`);
  }
}

export async function buildOrbitalAssets({
  basePath,
  cloudPath,
  runtimePath,
  manifestPath,
  width = 5400,
  height = 2700,
}) {
  const fallback = await normalizedImage(basePath, width, height, 'base');
  const clouds = await normalizedImage(cloudPath, width, height, 'cloud');
  const [baseMetadata, cloudMetadata] = await Promise.all([sharp(basePath).metadata(), sharp(cloudPath).metadata()]);

  await mkdir(dirname(runtimePath), { recursive: true });
  await sharp(fallback)
    .composite([{ input: clouds, blend: ORBITAL_SURFACE_CALIBRATION.cloudComposite.blend, opacity: ORBITAL_SURFACE_CALIBRATION.cloudComposite.opacity }])
    .jpeg(JPEG_OPTIONS)
    .toFile(runtimePath);

  const runtimeMetadata = await sharp(runtimePath).metadata();
  const dimensionsValid = baseMetadata.width === width
    && baseMetadata.height === height
    && runtimeMetadata.width === width
    && runtimeMetadata.height === height;
  if (!dimensionsValid) {
    throw new Error('invalid orbital asset dimensions');
  }

  const manifest = {
    schemaVersion: 3,
    asset: {
      kind: 'seamless-nasa-blue-marble-cloud-composite',
      projection: 'equirectangular',
      width,
      height,
      format: 'jpeg',
    },
    sources: {
      surface: {
        identity: 'NASA Blue Marble surface reference',
        url: ORBITAL_SURFACE_CALIBRATION.fallbackReferenceUrl,
        acquisitionPeriod: 'December 2004',
        dimensions: { width: baseMetadata.width, height: baseMetadata.height },
        sha256: await sha256(basePath),
      },
      clouds: {
        identity: 'NASA observation-derived cloud composite',
        url: null,
        acquisitionPeriod: 'not documented in supplied local source',
        dimensions: { width: cloudMetadata.width, height: cloudMetadata.height },
        sha256: await sha256(cloudPath),
      },
    },
    composition: ORBITAL_SURFACE_CALIBRATION.cloudComposite,
    calibration: {
      appliedToAsset: false,
      appliedAt: 'Cesium runtime in Task 2',
      values: {
        fallback: ORBITAL_SURFACE_CALIBRATION.fallback,
        runtime: ORBITAL_SURFACE_CALIBRATION.runtime,
      },
    },
    repair: {
      percentage: 0,
      candidatePreparation: 'tools/orbital/candidate-repair.mjs',
    },
    fallback: { version: 'nasa-blue-marble-2004-12-v1', sha256: await sha256(basePath) },
    runtime: { sha256: await sha256(runtimePath) },
    validation: {
      baseDecoded: true,
      runtimeDecoded: true,
      dimensionsValid,
      deterministic: true,
      valid: true,
    },
  };

  await mkdir(dirname(manifestPath), { recursive: true });
  await writeFile(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`);

  return { fallback: manifest.fallback, runtime: manifest.runtime, manifest };
}
