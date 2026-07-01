<?php

/**
 * Aplica migraciones SQL versionadas desde sql/migrations/.
 * Sustituye CREATE TABLE dispersos en helpers para instalaciones nuevas.
 */

function hay_schema_migrations_dir(): string
{
    return dirname(__DIR__) . '/sql/migrations';
}

/** @return list<string> rutas .sql ordenadas */
function hay_schema_migration_files(): array
{
    $dir = hay_schema_migrations_dir();
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . '/*.sql') ?: [];
    sort($files, SORT_NATURAL);

    return $files;
}

function hay_schema_migration_id(string $path): string
{
    return pathinfo($path, PATHINFO_FILENAME);
}

function hay_schema_migration_aplicada(PDO $pdo, string $id): bool
{
    hay_meta_ensure_table($pdo);
    $key = 'schema_sql_' . $id;

    return hay_meta_get($pdo, $key) === '1';
}

function hay_schema_migration_marcar(PDO $pdo, string $id): void
{
    hay_meta_set($pdo, 'schema_sql_' . $id, '1');
}

/** Ejecuta sentencias SQL de un archivo (sin prepared multi-query frágil). */
function hay_schema_ejecutar_sql(PDO $pdo, string $sql): void
{
    $sql = preg_replace('/--.*$/m', '', $sql) ?? $sql;
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql) ?? $sql;
    $parts = preg_split('/;\s*\n/', $sql) ?: [];
    foreach ($parts as $chunk) {
        $stmt = trim($chunk);
        if ($stmt === '' || stripos($stmt, 'SELECT 1') === 0) {
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Duplicate') !== false
                || stripos($msg, 'already exists') !== false
                || stripos($msg, 'check that column/key exists') !== false) {
                continue;
            }
            throw $e;
        }
    }
}

/** Aplica migraciones SQL pendientes. */
function hay_schema_aplicar_migraciones(PDO $pdo): void
{
    foreach (hay_schema_migration_files() as $file) {
        $id = hay_schema_migration_id($file);
        if (hay_schema_migration_aplicada($pdo, $id)) {
            continue;
        }
        $sql = file_get_contents($file);
        if ($sql === false || trim($sql) === '') {
            continue;
        }
        try {
            hay_schema_ejecutar_sql($pdo, $sql);
            hay_schema_migration_marcar($pdo, $id);
        } catch (Throwable $e) {
            error_log('HAY SQL migration [' . $id . ']: ' . $e->getMessage());
        }
    }
}

/** Cuando las migraciones SQL ya corrieron, evita DDL pesado en cada request. */
function hay_schema_ddl_habilitado(PDO $pdo): bool
{
    return hay_meta_get($pdo, 'schema_ddl_runtime') !== '0';
}

function hay_schema_deshabilitar_ddl_runtime(PDO $pdo): void
{
    hay_meta_set($pdo, 'schema_ddl_runtime', '0');
}
