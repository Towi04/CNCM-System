<?php
/**
 * Purga grupos de un plantel para reintentar Migración CSV limpia.
 *
 * CLI:
 *   php scripts/purge_grupos_plantel.php --plantel=guerrero --confirm="BORRAR GRUPOS" --todos
 *   php scripts/purge_grupos_plantel.php --id=3 --confirm="BORRAR GRUPOS"
 *   php scripts/purge_grupos_plantel.php --plantel=guerrero --confirm="BORRAR GRUPOS" --todos --borrar-alumnos
 *
 * --todos          borra TODOS los grupos del plantel (no solo el mapa migración)
 * --borrar-alumnos también borra alumnos mapeados por migración CSV en ese plantel
 */
declare(strict_types=1);

const PURGE_GRUPOS_CONFIRM = 'BORRAR GRUPOS';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Solo CLI.\n");
    exit(1);
}

if (!defined('HAY_SKIP_SCHEMA_BOOTSTRAP')) {
    define('HAY_SKIP_SCHEMA_BOOTSTRAP', true);
}
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../php/migracion_csv_helper.php';
require_once __DIR__ . '/../php/grupo_clave_helper.php';

$confirm = '';
$slug = '';
$idPlantel = 0;
$todos = false;
$borrarAlumnos = false;

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--confirm=')) {
        $confirm = substr($arg, 10);
    } elseif (str_starts_with($arg, '--plantel=')) {
        $slug = strtolower(trim(substr($arg, 10)));
    } elseif (str_starts_with($arg, '--id=')) {
        $idPlantel = (int) substr($arg, 5);
    } elseif ($arg === '--todos') {
        $todos = true;
    } elseif ($arg === '--borrar-alumnos') {
        $borrarAlumnos = true;
    }
}

if ($confirm !== PURGE_GRUPOS_CONFIRM) {
    fwrite(STDERR, 'Debe confirmar con --confirm="' . PURGE_GRUPOS_CONFIRM . '"' . "\n");
    fwrite(STDERR, "Ejemplo:\n  php scripts/purge_grupos_plantel.php --plantel=guerrero --confirm=\"BORRAR GRUPOS\" --todos\n");
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
    $stC = $pdo->prepare('SELECT COUNT(*) FROM grupos WHERE id_plantel = ?');
    $stC->execute([$idPlantel]);
    $nAntes = (int) $stC->fetchColumn();
} catch (Throwable $e) {
    fwrite(STDERR, 'No se pudo contar grupos: ' . $e->getMessage() . "\n");
    exit(1);
}
echo "Grupos actuales en plantel: {$nAntes}\n";
echo 'Modo: ' . ($todos ? 'TODOS los grupos del plantel' : 'solo mapa migración CSV') . "\n";
if ($borrarAlumnos) {
    echo "También borrará alumnos mapeados por migración.\n";
}

$res = migracion_csv_purgar_grupos_plantel($pdo, $idPlantel, $todos, $borrarAlumnos);
echo ($res['message'] ?? '') . "\n";
if (!empty($res['errores'])) {
    foreach ($res['errores'] as $err) {
        fwrite(STDERR, "  - {$err}\n");
    }
}

$stC->execute([$idPlantel]);
$nDespues = (int) $stC->fetchColumn();
echo "Grupos restantes: {$nDespues}\n";

exit(!empty($res['ok']) ? 0 : 1);
