import * as ImageManipulator from 'expo-image-manipulator';
import * as FileSystem from 'expo-file-system';

const DEFAULTS = {
  maxLongEdge: 1280,
  startQuality: 0.65,
  targetBytes: 400 * 1024,
  minQuality: 0.35,
  step: 0.1,
};

function inferName(asset, fallback = 'image.jpg') {
  if (asset?.fileName) return asset.fileName;
  if (asset?.name) return asset.name;
  return fallback;
}

function clamp(n, min, max) {
  return Math.max(min, Math.min(max, n));
}

function resizeActions({ width, height }, maxLongEdge) {
  const w = Number(width || 0);
  const h = Number(height || 0);
  if (!w || !h) return [];

  const longEdge = Math.max(w, h);
  if (longEdge <= maxLongEdge) return [];

  const scale = maxLongEdge / longEdge;
  const newW = Math.max(1, Math.round(w * scale));
  const newH = Math.max(1, Math.round(h * scale));
  return [{ resize: { width: newW, height: newH } }];
}

async function fileSizeBytes(uri) {
  try {
    const info = await FileSystem.getInfoAsync(uri, { size: true });
    return Number(info?.size || 0);
  } catch {
    return 0;
  }
}

/**
 * Accepts any image size and returns a JPEG asset suitable for uploads.
 * - Resizes to fit within maxLongEdge
 * - Compresses iteratively until <= targetBytes (or quality floor)
 */
export async function compressImageAsset(inputAsset, opts = {}) {
  const cfg = { ...DEFAULTS, ...opts };
  const uri = inputAsset?.uri;
  if (!uri) throw new Error('compressImageAsset: missing uri');

  const baseActions = resizeActions(inputAsset, cfg.maxLongEdge);
  const fallbackName = inferName(inputAsset, `image-${Date.now()}.jpg`);

  let quality = clamp(cfg.startQuality, cfg.minQuality, 0.95);
  let best = null;

  // Iteratively compress until under targetBytes or minQuality reached.
  // NOTE: manipulateAsync strips most metadata and outputs a new file.
  for (let i = 0; i < 10; i++) {
    const out = await ImageManipulator.manipulateAsync(
      uri,
      baseActions,
      { compress: quality, format: ImageManipulator.SaveFormat.JPEG }
    );

    const size = await fileSizeBytes(out.uri);
    best = {
      uri: out.uri,
      width: out.width,
      height: out.height,
      fileSize: size || undefined,
      mimeType: 'image/jpeg',
      fileName: fallbackName.endsWith('.jpg') || fallbackName.endsWith('.jpeg') ? fallbackName : `${fallbackName}.jpg`,
      originalUri: uri,
    };

    if (size && size <= cfg.targetBytes) break;
    if (quality <= cfg.minQuality + 1e-6) break;
    quality = clamp(quality - cfg.step, cfg.minQuality, 0.95);
  }

  return best;
}

