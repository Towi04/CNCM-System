#!/usr/bin/env bash
# Recreate HAY config.local.php on the GCP VM for prod + staging.
# Run once as a sudo-capable user:
#   sudo bash scripts/gcp/fix_hay_db_config.sh
# Or copy-paste after cloning develop.
#
# Optional env:
#   DB_PASS='tu_clave'   — if omitted, tries Moodle config.php then prompts
#   DB_USER=cncm
set -euo pipefail

DB_USER="${DB_USER:-cncm}"
PROD_DIR="${PROD_DIR:-/var/www/sistema}"
STAGING_DIR="${STAGING_DIR:-/var/www/sistema-pruebas}"
DB_PROD="${DB_PROD:-hay_system}"
DB_STAGING="${DB_STAGING:-hay_system_pruebas}"
MOODLE_CFG="${MOODLE_CFG:-/var/www/moodle/config.php}"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Ejecute con sudo: sudo bash $0" >&2
  exit 1
fi

detect_pass() {
  if [[ -n "${DB_PASS:-}" ]]; then
    echo "$DB_PASS"
    return
  fi
  # Reuse Moodle DB password if same MariaDB user
  if [[ -f "$MOODLE_CFG" ]]; then
    local moodle_user moodle_pass
    moodle_user=$(php -r "include '$MOODLE_CFG'; echo \$CFG->dbuser ?? '';" 2>/dev/null || true)
    moodle_pass=$(php -r "include '$MOODLE_CFG'; echo \$CFG->dbpass ?? '';" 2>/dev/null || true)
    if [[ "$moodle_user" == "$DB_USER" && -n "$moodle_pass" ]]; then
      echo "==> Usando la misma clave MariaDB que Moodle ($DB_USER)" >&2
      echo "$moodle_pass"
      return
    fi
  fi
  echo "==> No se encontró clave. Introduzca la de MariaDB para ${DB_USER}@localhost:" >&2
  read -r -s pass
  echo >&2
  echo "$pass"
}

DB_PASS="$(detect_pass)"
if [[ -z "$DB_PASS" ]]; then
  echo "ERROR: DB_PASS vacío" >&2
  exit 1
fi

echo "==> Asegurar usuario y bases"
mariadb -e "CREATE DATABASE IF NOT EXISTS \`${DB_PROD}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mariadb -e "CREATE DATABASE IF NOT EXISTS \`${DB_STAGING}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mariadb -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';" 2>/dev/null \
  || mariadb -e "ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mariadb -e "GRANT ALL ON \`${DB_PROD}\`.* TO '${DB_USER}'@'localhost';"
mariadb -e "GRANT ALL ON \`${DB_STAGING}\`.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"

write_config() {
  local dir="$1" dbname="$2"
  mkdir -p "$dir"
  # Backup any existing file (even empty)
  if [[ -e "$dir/config.local.php" ]]; then
    cp -a "$dir/config.local.php" "$dir/config.local.php.bak.$(date +%s)" || true
  fi
  # Remove Neubox .env that would override
  if [[ -f "$dir/.env" ]] && grep -q 'cncmedum_tovar\|cncmedum_hay_system' "$dir/.env" 2>/dev/null; then
    mv "$dir/.env" "$dir/.env.bak.neubox.$(date +%s)"
    echo "==> Movido .env Neubox en $dir"
  fi

  cat > "$dir/config.local.php" <<EOF
<?php
/** GCP — generado por scripts/gcp/fix_hay_db_config.sh */
define('HAY_DB_HOST', 'localhost');
define('HAY_DB_NAME', '${dbname}');
define('HAY_DB_USER', '${DB_USER}');
define('HAY_DB_PASS', '${DB_PASS}');

/** Legado Laravel (misma MariaDB) */
define('LEGACY_DB_HOST', '127.0.0.1');
define('LEGACY_DB_NAME', 'cncm_legado');
define('LEGACY_DB_USER', '${DB_USER}');
define('LEGACY_DB_PASS', '${DB_PASS}');

/** Moodle */
// define('MOODLE_URL', 'https://aula.cncm.edu.mx');
// define('MOODLE_TOKEN', '');

/** Google Workspace */
// define('GOOGLE_KEY_FILE', __DIR__ . '/google_key.json');
// define('GOOGLE_ADMIN_EMAIL', 'englishcoordinator@cncm.edu.mx');
// define('GOOGLE_WORKSPACE_DOMAIN', 'cncm.edu.mx');
EOF

  chown deploy:www-data "$dir/config.local.php" 2>/dev/null || chown www-data:www-data "$dir/config.local.php"
  chmod 640 "$dir/config.local.php"
  echo "==> Escrito $dir/config.local.php → ${dbname} / ${DB_USER}"
}

write_config "$PROD_DIR" "$DB_PROD"
write_config "$STAGING_DIR" "$DB_STAGING"

echo "==> Probar PDO staging"
sudo -u www-data php -r "
require '${STAGING_DIR}/config.local.php';
\$pdo = new PDO('mysql:host='.HAY_DB_HOST.';dbname='.HAY_DB_NAME.';charset=utf8mb4', HAY_DB_USER, HAY_DB_PASS);
echo 'OK staging: '.HAY_DB_NAME.' as '.HAY_DB_USER.PHP_EOL;
"

echo "==> Probar PDO prod"
sudo -u www-data php -r "
require '${PROD_DIR}/config.local.php';
\$pdo = new PDO('mysql:host='.HAY_DB_HOST.';dbname='.HAY_DB_NAME.';charset=utf8mb4', HAY_DB_USER, HAY_DB_PASS);
echo 'OK prod: '.HAY_DB_NAME.' as '.HAY_DB_USER.PHP_EOL;
"

echo "Listo. Recargue https://pruebas.sistema.cncm.edu.mx/"
