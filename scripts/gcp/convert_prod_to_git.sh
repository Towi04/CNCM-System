#!/usr/bin/env bash
# Convert an unzipped production tree into a git checkout of main,
# preserving config.local.php / uploads / storage.
set -euo pipefail

PROD_DIR="${PROD_DIR:-/var/www/sistema}"
REPO_URL="${REPO_URL:-https://github.com/Towi04/CNCM-System.git}"
BRANCH="${BRANCH:-main}"
TMP_DIR="/tmp/hay-prod-git-$$"

echo "==> Backing up server-only files"
mkdir -p "$TMP_DIR"
for f in config.local.php config.mail.php .env google_key.json; do
  [[ -f "$PROD_DIR/$f" ]] && cp -a "$PROD_DIR/$f" "$TMP_DIR/"
done
[[ -d "$PROD_DIR/uploads" ]] && sudo cp -a "$PROD_DIR/uploads" "$TMP_DIR/uploads"
[[ -d "$PROD_DIR/storage" ]] && sudo cp -a "$PROD_DIR/storage" "$TMP_DIR/storage"

echo "==> Replace tree with git clone (${BRANCH})"
sudo mkdir -p "${PROD_DIR}.bak"
sudo rsync -a --delete --exclude '.git' "$PROD_DIR/" "${PROD_DIR}.bak/" || true
sudo rm -rf "$PROD_DIR"
sudo git clone --branch "$BRANCH" "$REPO_URL" "$PROD_DIR"

echo "==> Restore server-only files"
for f in config.local.php config.mail.php .env google_key.json; do
  [[ -f "$TMP_DIR/$f" ]] && sudo cp -a "$TMP_DIR/$f" "$PROD_DIR/"
done
[[ -d "$TMP_DIR/uploads" ]] && sudo rsync -a "$TMP_DIR/uploads/" "$PROD_DIR/uploads/"
[[ -d "$TMP_DIR/storage" ]] && sudo rsync -a "$TMP_DIR/storage/" "$PROD_DIR/storage/"

sudo chown -R www-data:www-data "$PROD_DIR"
[[ -f "$PROD_DIR/config.local.php" ]] && sudo chmod 640 "$PROD_DIR/config.local.php"
sudo systemctl reload apache2 || true
rm -rf "$TMP_DIR"

echo "==> Production is now a git checkout at $(sudo git -C "$PROD_DIR" rev-parse --short HEAD)"
echo "    Backup of previous tree: ${PROD_DIR}.bak"
