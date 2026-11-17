#!/usr/bin/env bash
# Deploy HAY System on the GCP VM.
# Usage (on the server, as deploy user with passwordless sudo for chown/chmod/systemctl):
#   TARGET=staging REF=develop bash scripts/gcp/deploy_release.sh
#   TARGET=production REF=main bash scripts/gcp/deploy_release.sh
set -euo pipefail

TARGET="${TARGET:-staging}"
REF="${REF:-}"
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
# User that runs git (GitHub Actions SSH user). Defaults to current user.
DEPLOY_USER="${DEPLOY_USER:-$(id -un)}"

echo "==> Deploy target=${TARGET} ref=${REF} dir=${APP_DIR} as ${DEPLOY_USER}"

if [[ ! -d "$APP_DIR/.git" ]]; then
  echo "ERROR: ${APP_DIR} is not a git checkout. Clone first (see scripts/gcp/bootstrap_staging.sh)." >&2
  exit 1
fi

cd "$APP_DIR"

# Ensure deploy can write the tree (previous runs may have left www-data ownership)
sudo chown -R "${DEPLOY_USER}:${WEB_USER}" "$APP_DIR"
sudo chmod -R u+rwX,g+rwX "$APP_DIR"

# Preserve server-only files (may be mode 640 / not readable by deploy)
preserve=(config.local.php config.mail.php .env google_key.json)
PRESERVE_DIR="/tmp/hay-preserve-${TARGET}-$$"
mkdir -p "$PRESERVE_DIR"
for f in "${preserve[@]}"; do
  if sudo test -f "$APP_DIR/$f"; then
    sudo cp -a "$APP_DIR/$f" "$PRESERVE_DIR/$f"
    sudo chown "${DEPLOY_USER}:${DEPLOY_USER}" "$PRESERVE_DIR/$f"
  fi
done

if [[ "$TARGET" == "production" ]]; then
  mkdir -p storage
  date -Is | sudo tee "$MAINT_FLAG" >/dev/null
  sudo chown "${DEPLOY_USER}:${WEB_USER}" "$MAINT_FLAG"
  echo "==> Maintenance ON"
fi

git remote get-url origin >/dev/null 2>&1 || git remote add origin "$REPO_HTTPS_URL"
git fetch --prune origin
# -f discards local edits on the server (e.g. manual tweaks to this script)
git checkout -f -B "$REF" "origin/$REF"
git reset --hard "origin/$REF"
git clean -fd --exclude=config.local.php --exclude=config.mail.php --exclude=.env --exclude=google_key.json --exclude=uploads --exclude=storage --exclude=vendor

for f in "${preserve[@]}"; do
  if [[ -f "$PRESERVE_DIR/$f" ]]; then
    # Restore with sudo so mode/owner of directory cannot block write
    sudo cp -a "$PRESERVE_DIR/$f" "$APP_DIR/$f"
  fi
done
rm -rf "$PRESERVE_DIR"

if command -v composer >/dev/null 2>&1; then
  if [[ -f composer.json ]]; then
    echo "==> composer install"
    sudo -u "$WEB_USER" composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null \
      || composer install --no-dev --optimize-autoloader --no-interaction \
      || true
  fi
fi

# deploy owns the tree (so next git deploy works); www-data group can read/write app data
sudo chown -R "${DEPLOY_USER}:${WEB_USER}" "$APP_DIR"
sudo find "$APP_DIR" -type d -exec chmod 775 {} \;
sudo find "$APP_DIR" -type f -exec chmod 664 {} \;
# Secrets: owner deploy, group www-data (Apache can read; deploy can update next run)
for f in config.local.php config.mail.php .env google_key.json; do
  if [[ -f "$APP_DIR/$f" ]]; then
    sudo chown "${DEPLOY_USER}:${WEB_USER}" "$APP_DIR/$f"
    sudo chmod 640 "$APP_DIR/$f"
  fi
done
[[ -d "$APP_DIR/uploads" ]] && sudo chmod -R ug+rwX "$APP_DIR/uploads"

# PHP sessions: must stay group-writable for www-data after every deploy
sudo mkdir -p "$APP_DIR/storage/sessions"
sudo chown -R "${DEPLOY_USER}:${WEB_USER}" "$APP_DIR/storage"
sudo chmod -R ug+rwX "$APP_DIR/storage"
sudo chmod 2775 "$APP_DIR/storage" "$APP_DIR/storage/sessions"
# Drop stale session files that can block regenerate_id after ownership flips
sudo find "$APP_DIR/storage/sessions" -type f -name 'sess_*' -delete 2>/dev/null || true

# Reload PHP OPcache / Apache
if systemctl is-active --quiet apache2 2>/dev/null; then
  sudo systemctl reload apache2 || true
fi

if [[ "$TARGET" == "production" ]]; then
  sudo rm -f "$MAINT_FLAG"
  echo "==> Maintenance OFF"
fi

echo "==> Deploy OK: $(git rev-parse --short HEAD) ($REF)"
