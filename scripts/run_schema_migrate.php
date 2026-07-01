<?php
/**
 * Ejecuta migraciones SQL pendientes (CLI).
 * Uso: php scripts/run_schema_migrate.php
 */
define('HAY_RUN_SCHEMA_BOOTSTRAP', true);
require dirname(__DIR__) . '/config.php';

echo "Aplicando migraciones SQL...\n";
hay_schema_aplicar_migraciones($pdo);
if (function_exists('rbac_db_reparar_roles_sistema')) {
    rbac_db_reparar_roles_sistema($pdo);
    echo "RBAC reparado.\n";
}
echo "schema_bootstrap_version: " . (hay_meta_get($pdo, 'schema_bootstrap_version') ?? '(vacío)') . "\n";
echo "schema_ddl_runtime: " . (hay_meta_get($pdo, 'schema_ddl_runtime') ?? '(vacío)') . "\n";
echo "Listo.\n";
