# AGENTS.md

## Cursor Cloud specific instructions

Sistema HAY / Portal CNCM is a monolithic **PHP 8.3 + MariaDB** server-rendered web
app (Spanish UI). There is no build step and no Node toolchain. Composer manages PHP
deps; the app talks to MySQL/MariaDB via PDO. Dependency install runs automatically via
the startup update script (`composer install || composer update`). The notes below are
the non-obvious things a startup script does NOT handle.

### Services (must be started manually each session — not in the update script)

1. **MariaDB** (systemd is not running in this VM, start the daemon directly):
   ```bash
   sudo mkdir -p /run/mysqld && sudo chown mysql:mysql /run/mysqld
   sudo mariadbd --user=mysql   # run under tmux/background; listens on 127.0.0.1:3306
   ```
   DB: `cncmedum_hay_system`, user `hay_user` / `hay_pass_local` (matches
   `config.local.php`). If the data dir is empty, first run
   `sudo mariadb-install-db --user=mysql --datadir=/var/lib/mysql`.

2. **PHP dev server** (serve the repo root; the app is served from `/`):
   ```bash
   php -S 0.0.0.0:8000    # run under tmux/background, from the repo root
   ```
   Login page: `http://127.0.0.1:8000/index.php`, dashboard: `dashboard.php`.

### Required config file (gitignored, must exist)

`config.local.php` at the repo root is **required** and gitignored (see
`config.local.php.example`). It must define `HAY_DB_HOST`/`HAY_DB_NAME`/`HAY_DB_USER`/
`HAY_DB_PASS`. A dev copy already exists in this VM; recreate it if missing.

### Database setup (fresh DB only) — use `scripts/dev_setup_db.php`

The app was built against a long-lived production DB, so its own on-request schema
bootstrap (`hay_bootstrap_schema` in `config.php`) **cannot** initialize a *fresh, empty*
database — it deadlocks and infinitely recurses. Do NOT rely on it for a new DB. Instead:

```bash
php scripts/dev_setup_db.php        # idempotent; builds ~155 tables
php scripts/seed_datos_prueba.php   # demo users/groups/students (password 1234)
php scripts/seed_datos_operativos.php   # optional: payments/attendance/grades
```

Why the app's own bootstrap fails on an empty DB (all handled by `dev_setup_db.php`):
- `usuarios` has **no** `CREATE TABLE` anywhere in the repo (production-only base table);
  `alumno_grupos` is referenced by `plantel_ensure_schema`'s backfill before any helper
  creates it. Both are provided by `sql/dev_base_schema.sql` (plus `sql/school_schema.sql`
  for `grupos`/`alumnos`). `usuarios` needs an `activo` column (used by
  `gerente_ids_plantel`), included in `dev_base_schema.sql`.
- `profesor_360_ensure_schema` and `hay_eval_ensure_schema` each infinitely recurse via a
  seed whose guard flag is only written after the seed finishes; the script pre-sets those
  flags (`profesor_360_rubricas_v1`, `hay_area_asesor_ready`).
- `asesoria_ensure_schema` ↔ `asesoria_tabulador_ensure_defaults` is unconditional mutual
  recursion; the script runs every ensure_schema step **except** `asesoria_ensure_schema`
  (the asesoria tables come from migration `044_asesoria_schema`).
- `hay_schema_aplicar_migraciones()` sets `schema_ddl_runtime='0'`, which makes
  `plantel_ensure_schema` short-circuit; the script re-enables runtime DDL before each pass.

Harmless leftover warnings during setup: migrations `037/039/040` reference a `hay_meta`
table that does not exist (the real meta table is `hay_app_meta`) — these are no-op UPDATEs
that fail gracefully. A few migrations that depend on production-only columns also log and
continue. Login, dashboard, and pre-registro all work regardless.

### Demo credentials

Staff login e.g. `demo.g.deysi` / `1234` (Guerrero plantel, gerente). Students log in with
their número de control / `1234`. See `sql/SEED_DATOS_PRUEBA.md`.

### Lint / tests

- Syntax lint: `find . -path ./vendor -prune -o -name '*.php' -print | xargs -n1 php -l`.
- There is no automated unit-test suite in this repo.
