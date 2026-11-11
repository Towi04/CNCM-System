#!/usr/bin/env bash
# Deploy HAY System on the GCP VM.
# Usage (on the server, as a user with write access to the target dir):
#   TARGET=staging REF=develop bash scripts/gcp/deploy_release.sh
#   TARGET=production REF=main bash scripts/gcp/deploy_release.sh
set -euo pipefail

TARGET="${TARGET:-staging}"
REF="${REF:-}"
REPO_SSH_URL="${REPO_SSH_URL:-git@github.com:Towi04/CNCM-System.git}"
REPO_HTTPS_URL="${REPO_HTTPS_URL:-https://github.com/Towi04/CNCM-System.git}"

if [[ "$TARGET" == "production" ]]; then
  APP_DIR="${APP_DIR:-/var/www/sistema}"
  DEFAULT_REF="main"
elif [[ "$TARGET" == "staging" ]]; then
  APP_DIR="${APP_DIR:-/var/www/sistema-pruebas}"
  DEFAULT_REF="develop"
else
  echo "TARGET must be staging or production" >&2
  exit 1
fi

REF="${REF:-$DEFAULT_REF}"
MAINT_FLAG="${APP_DIR}/storage/maintenance.flag"
WEB_USER="${WEB_USER:-www-data}"

echo "==> Deploy target=${TARGET} ref=${REF} dir=${APP_DIR}"

if [[ ! -d "$APP_DIR/.git" ]]; then
  echo "ERROR: ${APP_DIR} is not a git checkout. Clone first (see scripts/gcp/bootstrap_staging.sh)." >&2
  exit 1
fi

cd "$APP_DIR"

# Preserve server-only files
preserve=(config.local.php config.mail.php .env google_key.json)
for f in "${preserve[@]}"; do
  if [[ -f "$f" ]]; then
    cp -a "$f" "/tmp/hay-preserve-${TARGET}-${f//\//_}"
  fi
done

if [[ "$TARGET" == "production" ]]; then
  mkdir -p storage
  date -Is > "$MAINT_FLAG"
  echo "==> Maintenance ON"
fi

# Prefer origin; fall back quietly
git remote get-url origin >/dev/null 2>&1 || git remote add origin "$REPO_HTTPS_URL"
git fetch --prune origin
git checkout -B "$REF" "origin/$REF"
git reset --hard "origin/$REF"

for f in "${preserve[@]}"; do
  bak="/tmp/hay-preserve-${TARGET}-${f//\//_}"
  if [[ -f "$bak" ]]; then
    cp -a "$bak" "$f"
    rm -f "$bak"
  fi
done

if command -v composer >/dev/null 2>&1; then
  if [[ -f composer.json ]]; then
    echo "==> composer install"
    sudo -u "$WEB_USER" composer install --no-dev --optimize-autoloader --no-interaction || \
      composer install --no-dev --optimize-autoloader --no-interaction
  fi
fi

# Permissions: keep config private; web tree readable by Apache
sudo chown -R "$WEB_USER:$WEB_USER" "$APP_DIR"
sudo find "$APP_DIR" -type d -exec chmod 755 {} \;
sudo find "$APP_DIR" -type f -exec chmod 644 {} \;
[[ -f "$APP_DIR/config.local.php" ]] && sudo chmod 640 "$APP_DIR/config.local.php"
[[ -d "$APP_DIR/uploads" ]] && sudo chmod -R ug+rwX "$APP_DIR/uploads"
[[ -d "$APP_DIR/storage" ]] && sudo chmod -R ug+rwX "$APP_DIR/storage"

# Reload PHP OPcache (validate_timestamps=0 in prod)
if systemctl is-active --quiet apache2; then
  sudo systemctl reload apache2 || true
fi

if [[ "$TARGET" == "production" ]]; then
  rm -f "$MAINT_FLAG"
  echo "==> Maintenance OFF"
fi

echo "==> Deploy OK: $(git rev-parse --short HEAD) ($REF)"
