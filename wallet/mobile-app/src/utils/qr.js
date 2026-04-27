/**
 * Shared QR payload helpers.
 *
 * The wallet uses a single QR format so one scanner can route every code:
 *
 *   base64( JSON.stringify({ t: 'payment', uid, name, extracash_number?, iat }) ) // user → payment
 *   base64( JSON.stringify({ t: 'product', pid, v: 1 })       )     // product
 *   base64( JSON.stringify({ t: 'buyfor',  token, v: 1 })     )     // buy-for-me request
 *
 * parseQr() is tolerant: if decoding fails we return null and callers can
 * show a "Invalid QR" message instead of crashing.
 */

// Fallback base64 decoder — Hermes exposes `atob` globally on modern Expo,
// but we keep a tiny polyfill for older runtimes / URL-safe variants.
const B64_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';

function safeAtob(input) {
  if (typeof input !== 'string') return null;
  // Accept url-safe base64 (-, _) and trim whitespace/newlines.
  const cleaned = input.replace(/-/g, '+').replace(/_/g, '/').replace(/\s/g, '');

  if (typeof globalThis.atob === 'function') {
    try { return globalThis.atob(cleaned); } catch { /* fall through */ }
  }

  // Manual decode — small enough to be fine for short QR payloads.
  const padded = cleaned + '==='.slice((cleaned.length + 3) % 4);
  let buffer = 0, bits = 0, out = '';
  for (let i = 0; i < padded.length; i++) {
    const c = padded.charAt(i);
    if (c === '=') break;
    const v = B64_CHARS.indexOf(c);
    if (v === -1) return null;
    buffer = (buffer << 6) | v;
    bits += 6;
    if (bits >= 8) {
      bits -= 8;
      out += String.fromCharCode((buffer >> bits) & 0xff);
    }
  }
  return out;
}

/**
 * Try to decode a raw QR string.
 * Returns one of:
 *   { kind: 'payment', uid: number, name?: string }
 *   { kind: 'product', pid: number, v?: number }
 *   { kind: 'buyfor',  token: string, v?: number }
 *   null
 */
export function parseQr(raw) {
  if (!raw || typeof raw !== 'string') return null;
  const decoded = safeAtob(raw);
  if (!decoded) return null;

  let obj;
  try { obj = JSON.parse(decoded); } catch { return null; }
  if (!obj || typeof obj !== 'object') return null;

  if (obj.t === 'payment' && obj.uid) {
    const rawEcn = obj.extracash_number ?? obj.ecn ?? null;
    const extracash_number =
      rawEcn == null || rawEcn === ''
        ? null
        : String(rawEcn);
    return {
      kind: 'payment',
      uid: Number(obj.uid),
      name: obj.name || null,
      extracash_number,
    };
  }
  if (obj.t === 'product' && obj.pid) {
    return { kind: 'product', pid: Number(obj.pid), v: obj.v || 1 };
  }
  if (obj.t === 'buyfor' && obj.token && typeof obj.token === 'string') {
    return { kind: 'buyfor', token: obj.token, v: obj.v || 1 };
  }
  return null;
}
