#!/bin/bash
# Demo container start: database settings, schema (db:build), demo setup, Apache.
# Variables: demo/.env.example. Passwords are never printed.
set -euo pipefail
cd /app/demo/project
echo "[demo] Starting (database, schema, demo setup)…"

# DATABASE_URL (mysql://user:pass@host:port/db) → SS_DATABASE_*; the demo uses its own
# database SILVERSTRIPE_DB_NAME on that server (Silverstripe creates it if missing).
if [ -n "${DATABASE_URL:-}" ]; then
  eval "$(php -r '
    $u = parse_url(getenv("DATABASE_URL"));
    $name = getenv("SILVERSTRIPE_DB_NAME") ?: "silverstripe";
    if (!preg_match("/^[A-Za-z0-9_]+$/", $name)) { fwrite(STDERR, "SILVERSTRIPE_DB_NAME may only contain letters, digits and _\n"); exit(1); }
    foreach (["SS_DATABASE_SERVER" => $u["host"] ?? "", "SS_DATABASE_PORT" => (string) ($u["port"] ?? 3306),
              "SS_DATABASE_USERNAME" => urldecode($u["user"] ?? ""), "SS_DATABASE_PASSWORD" => urldecode($u["pass"] ?? ""),
              "SS_DATABASE_NAME" => $name] as $k => $v) { echo "export $k=" . escapeshellarg($v) . "\n"; }
  ')"
fi
export SS_DATABASE_CLASS="${SS_DATABASE_CLASS:-MySQLDatabase}"
if [ -n "${RAILWAY_PUBLIC_DOMAIN:-}" ] && [ -z "${SS_BASE_URL:-}" ]; then
  export SS_BASE_URL="https://${RAILWAY_PUBLIC_DOMAIN}"
fi
export SS_BASE_URL="${SS_BASE_URL:-http://localhost:${PORT:-8080}}"
export SS_TRUSTED_PROXY_IPS="${SS_TRUSTED_PROXY_IPS:-*}"

# Later `vendor/bin/sake` calls (docker exec, Railway shell) and Apache read the same settings.
env | grep -E '^(SS_[A-Z_]+|TEMP_PATH|SUPERTEXT_[A-Z_]+)=' | sed -E "s/^([A-Z_]+)=(.*)$/\1='\2'/" > .env
chown www-data:www-data .env && chmod 600 .env

sake() { runuser -u www-data -- env DEMO_ADMIN_EMAIL="${DEMO_ADMIN_EMAIL:-}" DEMO_ADMIN_PASSWORD="${DEMO_ADMIN_PASSWORD:-}" \
  DEMO_EDITOR_EMAIL="${DEMO_EDITOR_EMAIL:-}" DEMO_EDITOR_PASSWORD="${DEMO_EDITOR_PASSWORD:-}" vendor/bin/sake "$@"; }

for attempt in $(seq 1 20); do
  if sake db:build --flush > /tmp/build.log 2>&1; then break; fi
  if [ "$attempt" = 20 ]; then echo "[demo] db:build failed:"; tail -30 /tmp/build.log; exit 1; fi
  echo "[demo] Database not ready yet, retrying…"; sleep 3
done
echo "[demo] Schema up to date."
sake tasks:supertext-demo-setup | grep '\[demo\]' || true

sed -ri "s/Listen [0-9]+/Listen ${PORT:-8080}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT:-8080}>/" /etc/apache2/sites-available/000-default.conf
echo "[demo] Starting Apache on port ${PORT:-8080}."
exec apache2-foreground
