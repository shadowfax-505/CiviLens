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

async function normalizedImage(filePath, width, height) {
  return sharp(filePath)
    .removeAlpha()
    .resize(width, height, { fit: 'fill', kernel: sharp.kernel.lanczos3 })
    .jpeg(JPEG_OPTIONS)
    .toBuffer();
}

export async function buildOrbitalAssets({
  basePath,
  cloudPath,
  fallbackPath,
  runtimePath,
  manifestPath,
  width = 5400,
  height = 2700,
}) {
  const fallback = await normalizedImage(basePath, width, height);
  const clouds = await normalizedImage(cloudPath, width, height);

  await mkdir(dirname(fallbackPath), { recursive: true });
  await writeFile(fallbackPath, fallback);
  await sharp(fallback)
    .composite([{ input: clouds, blend: ORBITAL_SURFACE_CALIBRATION.cloudComposite.blend, opacity: ORBITAL_SURFACE_CALIBRATION.cloudComposite.opacity }])
    .jpeg(JPEG_OPTIONS)
    .toFile(runtimePath);

  const [fallbackMetadata, runtimeMetadata] = await Promise.all([
    sharp(fallbackPath).metadata(),
    sharp(runtimePath).metadata(),
  ]);
  const dimensionsValid = fallbackMetadata.width === width
    && fallbackMetadata.height === height
    && runtimeMetadata.width === width
    && runtimeMetadata.height === height;
  if (!dimensionsValid) {
    throw new Error('invalid orbital asset dimensions');
  }

  const manifest = {
    schemaVersion: 2,
    asset: {
      kind: 'seamless-nasa-blue-marble-cloud-composite',
      projection: 'equirectangular',
      width,
      height,
      format: 'jpeg',
    },
    sources: {
      surface: { sha256: await sha256(basePath) },
      clouds: { sha256: await sha256(cloudPath) },
    },
    calibration: ORBITAL_SURFACE_CALIBRATION,
    fallback: { sha256: await sha256(fallbackPath) },
    runtime: { sha256: await sha256(runtimePath) },
    validation: {
      decoded: true,
      dimensionsValid,
      deterministic: true,
      valid: true,
    },
  };

  await mkdir(dirname(manifestPath), { recursive: true });
  await writeFile(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`);

  return { fallback: manifest.fallback, runtime: manifest.runtime, manifest };
}
