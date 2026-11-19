<?php
/**
 * Borra pagos importados por Migración CSV de un plantel (mapa + alumno_pagos).
 *
 * CLI:
 *   php scripts/purge_pagos_migracion.php --plantel=guerrero --confirm="BORRAR PAGOS"
 *   php scripts/purge_pagos_migracion.php --id=3 --confirm="BORRAR PAGOS"
 *   php scripts/purge_pagos_migracion.php --plantel=celaya --confirm="BORRAR PAGOS" --dry-run
 *
 * Solo elimina pagos presentes en hay_migracion_csv_map (entidad=pago) cuyo
 * alumno_pagos.id_plantel coincide con el plantel indicado.
 */
declare(strict_types=1);

const PURGE_PAGOS_CONFIRM = 'BORRAR PAGOS';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Solo CLI.\n");
    exit(1);
}

if (!defined('HAY_SKIP_SCHEMA_BOOTSTRAP')) {
    define('HAY_SKIP_SCHEMA_BOOTSTRAP', true);
}
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../php/migracion_csv_helper.php';

$confirm = '';
$slug = '';
$idPlantel = 0;
$dryRun = false;

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--confirm=')) {
        $confirm = substr($arg, 10);
    } elseif (str_starts_with($arg, '--plantel=')) {
        $slug = strtolower(trim(substr($arg, 10)));
    } elseif (str_starts_with($arg, '--id=')) {
        $idPlantel = (int) substr($arg, 5);
    } elseif ($arg === '--dry-run') {
        $dryRun = true;
    }
}

if ($confirm !== PURGE_PAGOS_CONFIRM) {
    fwrite(STDERR, 'Debe confirmar con --confirm="' . PURGE_PAGOS_CONFIRM . '"' . "\n");
    fwrite(STDERR, "Ejemplo:\n  php scripts/purge_pagos_migracion.php --plantel=guerrero --confirm=\"BORRAR PAGOS\"\n");
    exit(1);
}

if ($idPlantel <= 0 && $slug !== '') {
    $st = $pdo->prepare('SELECT id_plantel, nombre, slug FROM planteles WHERE slug = ? LIMIT 1');
    $st->execute([$slug]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    if (!$p) {
        fwrite(STDERR, "Plantel slug «{$slug}» no encontrado.\n");
        exit(1);
    }
    $idPlantel = (int) $p['id_plantel'];
    echo "Plantel: {$p['nombre']} ({$p['slug']}) #{$idPlantel}\n";
} elseif ($idPlantel > 0) {
    $st = $pdo->prepare('SELECT id_plantel, nombre, slug FROM planteles WHERE id_plantel = ? LIMIT 1');
    $st->execute([$idPlantel]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    if (!$p) {
        fwrite(STDERR, "Plantel id #{$idPlantel} no encontrado.\n");
        exit(1);
    }
    echo "Plantel: {$p['nombre']} ({$p['slug']}) #{$idPlantel}\n";
} else {
    fwrite(STDERR, "Indique --plantel=slug o --id=N\n");
    exit(1);
}

try {
    $res = migracion_csv_purge_pagos_plantel($pdo, $idPlantel, $dryRun);
    echo ($dryRun ? '[dry-run] ' : '') . ($res['message'] ?? '') . "\n";
    echo 'Pagos: ' . (int) ($res['pagos'] ?? 0)
        . ' · mapas: ' . (int) ($res['mapas'] ?? 0) . "\n";
    exit(!empty($res['ok']) ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
