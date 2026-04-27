import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';
import Constants from 'expo-constants';

function inferLanApiBaseUrl() {
  // Expo hostUri is usually like "172.20.10.2:8081" (LAN) or "localhost:8081" (simulator).
  const hostUri =
    Constants.expoConfig?.hostUri
    || Constants.manifest2?.extra?.expoClient?.hostUri
    || Constants.manifest?.hostUri
    || null;

  if (!hostUri || typeof hostUri !== 'string') return null;

  const host = hostUri.split(':')[0]?.trim();
  if (!host) return null;

  // Laravel default dev port used by this repo/dev script.
  return `http://${host}:8000`;
}

// Host only (e.g. http://192.168.x.x:8000). JSON routes use /api/v1 on this host.
const rawBase = (
  process.env.EXPO_PUBLIC_API_URL
  || (typeof __DEV__ !== 'undefined' && __DEV__ ? inferLanApiBaseUrl() : null)
  || 'http://localhost:8000'
).replace(/\/$/, '');
export const BASE_URL = rawBase;
export const API_ROOT = `${rawBase}/api/v1`;

let unauthorizedHandler = null;

export function setUnauthorizedHandler(handler) {
  unauthorizedHandler = handler;
}

function needsIdempotencyKey(url) {
  if (!url) return false;
  const u = url.split('?')[0].replace(/\/$/, '');
  if (u.endsWith('/wallet/send') || u.endsWith('/qr-pay') || u.endsWith('/checkout')) return true;
  if (/\/buy-requests\/[^/]+\/fulfill$/.test(u)) return true;
  if (/\/products\/\d+\/buy$/.test(u)) return true;
  return false;
}

function generateIdempotencyKey() {
  return `idem_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 14)}`;
}

function shouldRetryGet(error) {
  const cfg = error.config;
  if (!cfg || (cfg.method || 'get').toLowerCase() !== 'get') return false;
  if ((cfg.__retryCount || 0) >= 2) return false;
  if (!error.response) return true;
  const s = error.response.status;
  return [408, 429, 502, 503, 504].includes(s);
}

const api = axios.create({
  baseURL: API_ROOT,

  timeout: 12000,
  headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
});

// Attach stored token to every request automatically
api.interceptors.request.use(async (config) => {
  const token = await AsyncStorage.getItem('token');
  if (token) config.headers.Authorization = `Bearer ${token}`;

  const method = (config.method || 'get').toLowerCase();
  if (method === 'post' || method === 'put' || method === 'patch') {
    const rel = config.url || '';
    if (needsIdempotencyKey(rel) && !config.headers['Idempotency-Key']) {
      config.headers['Idempotency-Key'] = generateIdempotencyKey();
    }
  }

  return config;
});

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error?.response?.status === 401) {
      await AsyncStorage.removeItem('token');
      if (typeof unauthorizedHandler === 'function') {
        await unauthorizedHandler();
      }
    }

    const cfg = error.config;
    if (cfg && shouldRetryGet(error)) {
      cfg.__retryCount = (cfg.__retryCount || 0) + 1;
      const delay = 350 * cfg.__retryCount;
      await new Promise((r) => setTimeout(r, delay));
      return api(cfg);
    }

    return Promise.reject(error);
  }
);

export default api;
