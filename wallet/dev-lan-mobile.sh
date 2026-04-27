#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MOBILE_DIR="$ROOT_DIR/mobile-app"

LARAVEL_HOST="0.0.0.0"
LARAVEL_PORT="${LARAVEL_PORT:-8000}"

function detect_lan_ip() {
  # macOS: prefer Wi‑Fi (en0), then fallback.
  local ip=""
  ip="$(ipconfig getifaddr en0 2>/dev/null || true)"
  if [[ -z "${ip}" ]]; then
    ip="$(ipconfig getifaddr en1 2>/dev/null || true)"
  fi
  if [[ -z "${ip}" ]]; then
    # Last-resort: hostname -I isn't available on macOS, so use a route lookup.
    ip="$(route -n get default 2>/dev/null | awk '/interface:/{print $2}' | xargs -I{} ipconfig getifaddr {} 2>/dev/null || true)"
  fi
  echo "${ip}"
}

LAN_IP="$(detect_lan_ip)"
if [[ -z "${LAN_IP}" ]]; then
  echo "Could not detect your LAN IP. Connect to Wi‑Fi and try again."
  exit 1
fi

export EXPO_PUBLIC_API_URL="http://${LAN_IP}:${LARAVEL_PORT}"

echo ""
echo "Laravel will be reachable on your Wi‑Fi at:"
echo "  ${EXPO_PUBLIC_API_URL}"
echo ""
echo "Starting Laravel (host=${LARAVEL_HOST}, port=${LARAVEL_PORT}) and Expo (LAN + clear cache)."
echo "When Expo shows the QR code, scan it with your iPhone/Android on the same Wi‑Fi."
echo ""

LARAVEL_PID=""
EXPO_PID=""

cleanup() {
  [[ -n "${EXPO_PID}" ]] && kill "${EXPO_PID}" 2>/dev/null || true
  [[ -n "${LARAVEL_PID}" ]] && kill "${LARAVEL_PID}" 2>/dev/null || true
}
trap cleanup EXIT INT TERM

cd "${ROOT_DIR}"
php artisan serve --host="${LARAVEL_HOST}" --port="${LARAVEL_PORT}" >/dev/null 2>&1 &
LARAVEL_PID="$!"

cd "${MOBILE_DIR}"
# - `--lan` ensures the QR works on the same network
# - `-c` clears Metro cache so QR/session refreshes cleanly
npm run start:lan -- -c &
EXPO_PID="$!"

wait

