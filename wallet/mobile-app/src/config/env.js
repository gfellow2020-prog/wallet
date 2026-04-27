/**
 * Validates Expo public env at startup (dev-friendly warnings only).
 */
export function validateApiConfig() {
  const url = process.env.EXPO_PUBLIC_API_URL;
  if (typeof __DEV__ !== 'undefined' && __DEV__) {
    if (!url || !String(url).trim()) {
      console.warn(
        '[ExtraCash] EXPO_PUBLIC_API_URL is not set. Copy mobile-app/.env.example to .env and set your Laravel base URL, then restart Expo.',
      );
      return;
    }
    if (!/^https?:\/\//i.test(String(url))) {
      console.warn('[ExtraCash] EXPO_PUBLIC_API_URL should start with http:// or https:// — got:', url);
    }
  }
}
