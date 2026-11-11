#!/usr/bin/env bash
# One-time bootstrap of staging on the GCP VM (run with sudo-capable user).
# Creates /var/www/sistema-pruebas, DB hay_system_pruebas, Apache vhost, optional SSL.
set -euo pipefail

STAGING_DIR="${STAGING_DIR:-/var/www/sistema-pruebas}"
PROD_DIR="${PROD_DIR:-/var/www/sistema}"
DB_PROD="${DB_PROD:-hay_system}"
DB_STAGING="${DB_STAGING:-hay_system_pruebas}"
DB_USER="${DB_USER:-cncm}"
STAGING_HOST="${STAGING_HOST:-pruebas.sistema.cncm.edu.mx}"
REPO_URL="${REPO_URL:-https://github.com/Towi04/CNCM-System.git}"
BRANCH="${BRANCH:-develop}"

echo "==> Staging dir ${STAGING_DIR}"
sudo mkdir -p "$STAGING_DIR"

if [[ ! -d "$STAGING_DIR/.git" ]]; then
  # Prefer cloning develop; fall back to main if develop does not exist yet
  if git ls-remote --heads "$REPO_URL" "$BRANCH" | grep -q "$BRANCH"; then
    sudo git clone --branch "$BRANCH" "$REPO_URL" "$STAGING_DIR"
  else
    echo "==> Branch ${BRANCH} missing; cloning main"
    sudo git clone --branch main "$REPO_URL" "$STAGING_DIR"
    sudo git -C "$STAGING_DIR" checkout -B develop
  fi
fi

echo "==> Database ${DB_STAGING} (copy from ${DB_PROD})"
sudo mariadb -e "CREATE DATABASE IF NOT EXISTS \`${DB_STAGING}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mariadb -e "GRANT ALL ON \`${DB_STAGING}\`.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"
sudo mariadb-dump --single-transaction --routines --triggers "$DB_PROD" \
  | sudo mariadb "$DB_STAGING"

if [[ -f "$PROD_DIR/config.local.php" ]]; then
  sudo cp "$PROD_DIR/config.local.php" "$STAGING_DIR/config.local.php"
  sudo sed -i "s/${DB_PROD}/${DB_STAGING}/g" "$STAGING_DIR/config.local.php"
  # Prefer Gemini for student tutor on staging when keys are present
  if ! grep -q "HAY_AI_PROVIDER" "$STAGING_DIR/config.local.php"; then
    echo "" | sudo tee -a "$STAGING_DIR/config.local.php" >/dev/null
    echo "// define('HAY_AI_PROVIDER', 'gemini');" | sudo tee -a "$STAGING_DIR/config.local.php" >/dev/null
    echo "// define('GEMINI_API_KEY', 'pegue-clave-ai-studio');" | sudo tee -a "$STAGING_DIR/config.local.php" >/dev/null
  fi
else
  echo "AVISO: no hay ${PROD_DIR}/config.local.php — cree ${STAGING_DIR}/config.local.php a mano"
fi

sudo mkdir -p "$STAGING_DIR/storage" "$STAGING_DIR/uploads"
sudo chown -R www-data:www-data "$STAGING_DIR"
sudo chmod 640 "$STAGING_DIR/config.local.php" 2>/dev/null || true

VHOST="/etc/apache2/sites-available/sistema-pruebas.conf"
if [[ ! -f "$VHOST" ]]; then
  sudo tee "$VHOST" >/dev/null <<EOF
<VirtualHost *:80>
    ServerName ${STAGING_HOST}
    DocumentRoot ${STAGING_DIR}
    <Directory ${STAGING_DIR}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.php
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/sistema-pruebas-error.log
    CustomLog \${APACHE_LOG_DIR}/sistema-pruebas-access.log combined
</VirtualHost>
EOF
  sudo a2ensite sistema-pruebas.conf
  sudo apache2ctl configtest
  sudo systemctl reload apache2
  echo "==> Apache site enabled. Add DNS A ${STAGING_HOST} -> VM IP, then:"
  echo "    sudo certbot --apache -d ${STAGING_HOST}"
fi

echo "==> Bootstrap staging OK"
echo "    URL: http://${STAGING_HOST}"
echo "    DB:  ${DB_STAGING}"
echo "    Dir: ${STAGING_DIR}"
