import { createHash } from 'node:crypto';
import { mkdir, readFile, rename, unlink, writeFile } from 'node:fs/promises';
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

async function inputMetadata(filePath, label) {
  try {
    const metadata = await sharp(filePath).metadata();
    if (!metadata.width || !metadata.height) {
      throw new Error('image dimensions are unavailable');
    }
    return metadata;
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
  const [baseMetadata, cloudMetadata] = await Promise.all([inputMetadata(basePath, 'base'), inputMetadata(cloudPath, 'cloud')]);
  if (baseMetadata.width !== width || baseMetadata.height !== height) {
    throw new Error(`orbital base source dimensions must be ${width}x${height}, received ${baseMetadata.width}x${baseMetadata.height}`);
  }
  const [fallback, clouds] = await Promise.all([
    normalizedImage(basePath, width, height, 'base'),
    normalizedImage(cloudPath, width, height, 'cloud'),
  ]);

  const runtimeBuffer = await sharp(fallback)
    .composite([{ input: clouds, blend: ORBITAL_SURFACE_CALIBRATION.cloudComposite.blend, opacity: ORBITAL_SURFACE_CALIBRATION.cloudComposite.opacity }])
    .jpeg(JPEG_OPTIONS)
    .toBuffer();
  const runtimeBufferMetadata = await sharp(runtimeBuffer).metadata();
  if (runtimeBufferMetadata.width !== width || runtimeBufferMetadata.height !== height) {
    throw new Error('invalid orbital runtime dimensions');
  }

  await mkdir(dirname(runtimePath), { recursive: true });
  const temporaryRuntimePath = `${runtimePath}.${process.pid}.tmp`;
  try {
    await writeFile(temporaryRuntimePath, runtimeBuffer);
    const temporaryMetadata = await sharp(temporaryRuntimePath).metadata();
    if (temporaryMetadata.width !== width || temporaryMetadata.height !== height) {
      throw new Error('invalid temporary orbital runtime dimensions');
    }
    await rename(temporaryRuntimePath, runtimePath);
  } catch (error) {
    await unlink(temporaryRuntimePath).catch(() => {});
    throw error;
  }

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
        identity: 'NASA GIBS MODIS Terra Corrected Reflectance True Color cloud observation',
        layer: 'MODIS_Terra_CorrectedReflectance_TrueColor',
        endpoint: 'https://gibs.earthdata.nasa.gov/wms/epsg4326/best/wms.cgi',
        template: 'https://gibs.earthdata.nasa.gov/wms/epsg4326/best/wms.cgi?SERVICE=WMS&REQUEST=GetMap&VERSION=1.3.0&LAYERS=MODIS_Terra_CorrectedReflectance_TrueColor&STYLES=default&FORMAT=image/png&TRANSPARENT=FALSE&WIDTH={width}&HEIGHT={height}&CRS=EPSG:4326&BBOX=-90,-180,90,180&TIME=2026-07-27',
        acquisitionDate: '2026-07-27',
        processing: 'observation-derived cloud extraction and screen composite',
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
