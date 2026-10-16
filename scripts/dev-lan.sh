#!/usr/bin/env bash
# Serves the API to devices on this Wi-Fi and points the Nemchiw app at it.
#
# Phones and emulators cannot reach 127.0.0.1 or Herd's .test domains, so the API binds to
# 0.0.0.0 and both .env files get this Mac's LAN address (it changes between networks, so
# run this script again after switching). Restart Metro afterwards so the app reads API_URL.
#
#   composer dev:lan                 # default port 8000, app in ../nemchiw
#   PORT=8001 APP_DIR=~/nemchiw composer dev:lan
set -euo pipefail

cd "$(dirname "$0")/.."
PORT="${PORT:-8000}"
APP_DIR="${APP_DIR:-../nemchiw}"

IP="${LAN_IP:-$(ipconfig getifaddr en0 2>/dev/null || ipconfig getifaddr en1 2>/dev/null || true)}"
if [[ -z "$IP" ]]; then
  echo "No LAN address found (Wi-Fi off?). Set LAN_IP=… and run again." >&2
  exit 1
fi
BASE="http://$IP:$PORT"

# Replaces KEY=… in an env file, or appends it.
set_env() {
  local file="$1" key="$2" value="$3"
  if grep -q "^$key=" "$file"; then
    sed -i '' "s|^$key=.*|$key=$value|" "$file"
  else
    printf '%s=%s\n' "$key" "$value" >>"$file"
  fi
}

# Photo URLs are built from APP_URL, so it must be the address the phone uses.
set_env .env APP_URL "$BASE"
if [[ -f "$APP_DIR/.env" ]]; then
  set_env "$APP_DIR/.env" API_URL "$BASE/api/v1"
  echo "App:  $APP_DIR/.env → API_URL=$BASE/api/v1 (restart Metro: npm start)"
else
  echo "App .env not found at $APP_DIR/.env; set API_URL=$BASE/api/v1 there yourself." >&2
fi
php artisan config:clear >/dev/null

echo "API:  $BASE  (OTP codes are written to storage/logs/laravel.log)"

# The built-in server is single-threaded unless told otherwise; photos and API calls overlap.
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"
php artisan serve --host=0.0.0.0 --port="$PORT" --no-reload &
SERVER=$!
# Same scheduler as production: the queue worker every minute, pruning, backups.
php artisan schedule:work &
SCHEDULER=$!
trap 'kill $SERVER $SCHEDULER 2>/dev/null' EXIT INT TERM
wait
