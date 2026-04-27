import { BASE_URL } from '../services/client';

/**
 * Convert a public `/storage/...` URL to an on-the-fly optimized variant.
 * Keeps originals as fallback if URL isn't on our host or isn't a /storage/ URL.
 */
export function optImageUrl(url, { w = 900, q = 65 } = {}) {
  const raw = String(url || '');
  if (!raw) return raw;

  // Only optimize images served from our own backend.
  if (!raw.startsWith(BASE_URL)) return raw;

  const idx = raw.indexOf('/storage/');
  if (idx === -1) return raw;

  const rel = raw.slice(idx + '/storage/'.length);
  const path = rel.replace(/^\/+/, '');
  if (!path) return raw;

  const qs = `path=${encodeURIComponent(path)}&w=${encodeURIComponent(String(w))}&q=${encodeURIComponent(String(q))}`;
  return `${BASE_URL}/api/v1/media/image?${qs}`;
}

