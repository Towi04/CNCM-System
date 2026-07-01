<?php

/**
 * CLI: importar datos del sistema legado a HAY.
 *
 * php php/legacy_import_run.php
 * php php/legacy_import_run.php --fase=alumnos
 * php php/legacy_import_run.php --dry-run
 * php php/legacy_import_run.php --reset-map
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Ejecutar solo por línea de comandos.\n";
    exit(1);
}

define('HAY_SKIP_SCHEMA_BOOTSTRAP', false);

require dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/legacy_import_helper.php';

$opts = getopt('', ['fase:', 'dry-run', 'reset-map', 'help']);

if (isset($opts['help'])) {
    echo "Uso: php php/legacy_import_run.php [--fase=all|planteles|...] [--dry-run] [--reset-map]\n";
    echo "Requiere LEGACY_DB_* en config.local.php\n";
    exit(0);
}

$dryRun = isset($opts['dry-run']);
$fase = $opts['fase'] ?? 'all';

try {
    $leg = legacy_import_pdo_legacy();
    if (!$leg) {
        fwrite(STDERR, "Error: defina LEGACY_DB_HOST, LEGACY_DB_NAME, LEGACY_DB_USER, LEGACY_DB_PASS en config.local.php\n");
        exit(1);
    }

    legacy_import_ensure_schema($pdo);

    if (isset($opts['reset-map'])) {
        if ($dryRun) {
            echo "[dry-run] Se truncaría hay_legacy_map\n";
        } else {
            legacy_import_reset_map($pdo);
            echo "Mapa de importación reiniciado.\n";
        }
    }

    echo $dryRun ? "=== Simulación (dry-run) ===\n" : "=== Importación legado → HAY ===\n";
    $result = legacy_import_run($pdo, $leg, $fase, $dryRun);

    foreach ($result as $nombre => $stats) {
        printf(
            "%-22s insertados: %d  omitidos: %d  errores: %d\n",
            $nombre,
            $stats['inserted'],
            $stats['skipped'],
            $stats['errors']
        );
    }

    echo "\nRevise hay_legacy_import_log para detalle.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
