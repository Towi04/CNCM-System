<?php

/**
 * Idempotent LOCAL/DEV database bootstrap for Sistema HAY / Portal CNCM.
 *
 * The application was built against a long-lived production database, so a few
 * things do not work when bootstrapping a *fresh, empty* database via the app's
 * own on-request schema bootstrap (hay_bootstrap_schema in config.php):
 *
 *   1. The base `usuarios` table has no CREATE TABLE anywhere in the repo, and
 *      `alumno_grupos` is referenced by plantel_ensure_schema's backfill before
 *      any ensure_schema creates it (chicken-and-egg). See sql/dev_base_schema.sql.
 *   2. Two one-time seeds recurse infinitely on a fresh DB because their guard
 *      flag is only written AFTER the seed completes, while the seed re-enters
 *      ensure_schema:
 *        - profesor_360_ensure_schema  -> profesor_360_seed_rubricas_default
 *        - hay_eval_ensure_schema      -> hay_eval_asegurar_area_asesor
 *      We pre-set their guard flags so the seeds are skipped (the optional default
 *      rubricas / asesor area can still be created from the UI).
 *   3. asesoria_ensure_schema <-> asesoria_tabulador_ensure_defaults is an
 *      unconditional mutual recursion, so we run every ensure_schema step EXCEPT
 *      asesoria_ensure_schema. The asesoria tables come from migration
 *      044_asesoria_schema instead.
 *
 * After this runs, `schema_bootstrap_version` is set so the app skips the heavy
 * (and partially-broken) $pasos bootstrap on normal requests.
 *
 * Safe to run multiple times. Usage:  php scripts/dev_setup_db.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);

require_once $root . '/php/db_config_helper.php';
$creds = hay_db_credentials();

$dsn = "mysql:host={$creds['host']};dbname={$creds['db']};charset=utf8mb4";
$pdo = new PDO($dsn, $creds['user'], $creds['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

/** Run a .sql file made of simple `;`-terminated statements. */
function dev_run_sql_file(PDO $pdo, string $path): void
{
    $sql = (string) file_get_contents($path);
    $sql = preg_replace('/--.*$/m', '', $sql) ?? $sql;
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt === '') {
            continue;
        }
        $pdo->exec($stmt);
    }
}

echo "[1/5] Loading base schema (usuarios, alumno_grupos, school tables)...\n";
dev_run_sql_file($pdo, $root . '/sql/dev_base_schema.sql');
dev_run_sql_file($pdo, $root . '/sql/school_schema.sql');

echo "[2/5] Pre-setting recursion guard flags...\n";
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS hay_app_meta (
        clave VARCHAR(64) NOT NULL,
        valor VARCHAR(255) NOT NULL,
        actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (clave)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
$setFlag = $pdo->prepare(
    "INSERT INTO hay_app_meta (clave, valor) VALUES (?, '1')
     ON DUPLICATE KEY UPDATE valor = '1'"
);
foreach (['profesor_360_rubricas_v1', 'hay_area_asesor_ready', 'schema_ddl_runtime'] as $flag) {
    $setFlag->execute([$flag]);
}
// schema_ddl_runtime must be '1' (enabled) so DDL runs during this setup.
$pdo->exec("UPDATE hay_app_meta SET valor = '1' WHERE clave = 'schema_ddl_runtime'");

echo "[3/5] Loading helpers (config.php, skip on-request bootstrap)...\n";
define('HAY_SKIP_SCHEMA_BOOTSTRAP', true);
require $root . '/config.php';

echo "[4/5] Applying SQL migrations + ensure_schema (except recursive asesoria)...\n";

$fns = [
    'rbac_db_asegurar_jerarquia_roles', 'rbac_db_reparar_roles_sistema',
    'plantel_ensure_schema', 'catalog_ensure_schema', 'operativo_cncm_ensure_schema',
    'preregistro_ensure_schema', 'alumno_ensure_schema', 'asistencia_ensure_schema',
    'huella_ensure_schema', 'huella_fingerjet_ensure_schema', 'pago_ensure_schema',
    'fase_ensure_schema', 'academico_ensure_schema', 'profesor_portal_ensure_schema',
    'graduacion_ensure_schema', 'docente_prospecto_ensure_schema', 'expediente_documental_ensure_schema',
    'profesor_eval_ensure_schema', 'profesor_360_ensure_schema', 'hay_eval_ensure_schema',
    'reporte_semanal_ensure_schema', 'grupo_plan_ensure_schema', 'grupo_docente_ensure_schema',
    'grupo_fusion_ensure_schema', 'tutor_ensure_schema', 'aula_ensure_schema', 'rol_aula_ensure_schema',
    'calendario_migrate_schema', 'combo_ensure_schema', 'certificacion_ensure_schema',
    'asesor_ensure_schema', 'comision_cert_ensure_schema', 'referido_ensure_schema',
    'ventas_comision_ensure_schema', 'grupo_preinicio_ensure_schema', 'escuelas_ensure_schema',
    'usuario_ensure_schema', 'user_avatar_ensure_schema', 'rbac_db_ensure_schema',
    'login_security_ensure_schema', 'usuario_suspension_ensure_schema', 'soporte_ensure_schema',
    'legacy_import_ensure_schema',
];
// Several passes so tables/columns created in earlier passes unblock later ones.
// NOTE: hay_schema_aplicar_migraciones() sets schema_ddl_runtime='0', which makes
// the ensure_schema helpers short-circuit (plantel_ensure_schema returns early),
// so we MUST re-enable runtime DDL at the start of every pass.
for ($pass = 1; $pass <= 4; $pass++) {
    $pdo->exec("UPDATE hay_app_meta SET valor = '1' WHERE clave = 'schema_ddl_runtime'");
    foreach ($fns as $fn) {
        if (!function_exists($fn)) {
            continue;
        }
        try {
            $fn($pdo);
        } catch (Throwable $e) {
            error_log("dev_setup_db [$fn]: " . $e->getMessage());
        }
    }
    hay_schema_aplicar_migraciones($pdo);
}

echo "[5/5] Marking schema as bootstrapped...\n";
hay_meta_set($pdo, 'schema_bootstrap_version', (string) HAY_SCHEMA_VERSION);
hay_schema_deshabilitar_ddl_runtime($pdo);

$count = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()"
)->fetchColumn();
echo "Done. {$count} tables present in '{$creds['db']}'.\n";
