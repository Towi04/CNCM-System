#!/usr/bin/env bash
# Recreate HAY config.local.php on the GCP VM for prod + staging.
# Run once as a sudo-capable user:
#
#   # Preferido (sudo limpia el entorno; NO uses `sudo DB_PASS=... bash` por pipe):
#   curl -fsSL .../fix_hay_db_config.sh -o /tmp/fix_hay_db_config.sh
#   sudo bash /tmp/fix_hay_db_config.sh 'tu_clave'
#
#   # Alternativa con env preservado:
#   sudo env DB_PASS='tu_clave' bash /tmp/fix_hay_db_config.sh
#
# Si se omite la clave: intenta Moodle config.php; si no, pide input (solo TTY).
#   DB_USER=cncm (default)
set -euo pipefail

DB_USER="${DB_USER:-cncm}"
PROD_DIR="${PROD_DIR:-/var/www/sistema}"
STAGING_DIR="${STAGING_DIR:-/var/www/sistema-pruebas}"
DB_PROD="${DB_PROD:-hay_system}"
DB_STAGING="${DB_STAGING:-hay_system_pruebas}"
MOODLE_CFG="${MOODLE_CFG:-/var/www/moodle/config.php}"
# argv[1] beats env (sudo often strips DB_PASS from the environment)
if [[ -n "${1:-}" ]]; then
  DB_PASS="$1"
fi

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Ejecute con sudo: sudo bash $0 'tu_clave'" >&2
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
  if [[ ! -t 0 ]]; then
    echo "ERROR: falta la clave. Use: sudo bash $0 'tu_clave'" >&2
    echo "  (sudo limpia DB_PASS del entorno; no pegue la clave en el chat)." >&2
    exit 1
  fi
  echo "==> Introduzca la clave MariaDB para ${DB_USER}@localhost:" >&2
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

for dir in "$STAGING_DIR" "$PROD_DIR"; do
  if grep -q 'cncmedum_tovar\|cncmedum_hay_system' "$dir/config.local.php" 2>/dev/null; then
    echo "ERROR: $dir/config.local.php aún tiene credenciales Neubox" >&2
    exit 1
  fi
  if [[ -f "$dir/.env" ]] && grep -q 'cncmedum_tovar\|cncmedum_hay_system' "$dir/.env" 2>/dev/null; then
    echo "ERROR: $dir/.env aún tiene Neubox — muévalo a un .bak" >&2
    exit 1
  fi
done

echo "==> Probar PDO staging + prod (como www-data)"
for pair in "${STAGING_DIR}|staging" "${PROD_DIR}|prod"; do
  dir="${pair%%|*}"
  label="${pair##*|}"
  sudo -u www-data php -r "
  require '${dir}/config.local.php';
  echo '${label}: user='.HAY_DB_USER.' db='.HAY_DB_NAME.PHP_EOL;
  if (HAY_DB_USER === 'cncmedum_tovar') { fwrite(STDERR, \"ERROR Neubox user\\n\"); exit(1); }
  \$pdo = new PDO('mysql:host='.HAY_DB_HOST.';dbname='.HAY_DB_NAME.';charset=utf8mb4', HAY_DB_USER, HAY_DB_PASS);
  echo 'OK ${label}'.PHP_EOL;
  "
done

systemctl reload apache2 2>/dev/null || true
echo "Listo. Recargue https://pruebas.sistema.cncm.edu.mx/ (Ctrl+F5)."
