<?php
/**
 * Elimina datos operativos, pruebas e importación legacy.
 * Conserva: roles, planteles, catálogos, personal real, rúbrica HAY, bancos de exámenes.
 *
 * CLI:
 *   php scripts/purge_datos_operativos.php --confirm=BORRAR DATOS
 *   php scripts/purge_datos_operativos.php --confirm=BORRAR DATOS --solo-demo
 *   php scripts/purge_datos_operativos.php --confirm=BORRAR DATOS --legacy-catalogo
 */
declare(strict_types=1);

if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (!defined('HAY_SKIP_SCHEMA_BOOTSTRAP')) {
        define('HAY_SKIP_SCHEMA_BOOTSTRAP', true);
    }
    if (!defined('HAY_ROOT')) {
        require __DIR__ . '/../config.php';
    }
}

require_once __DIR__ . '/../php/purge_operativo_helper.php';

$confirm = '';
$soloDemo = false;
$legacyCatalogo = false;

if (PHP_SAPI === 'cli') {
    foreach ($argv ?? [] as $arg) {
        if (str_starts_with($arg, '--confirm=')) {
            $confirm = substr($arg, 10);
        }
        if ($arg === '--solo-demo') {
            $soloDemo = true;
        }
        if ($arg === '--legacy-catalogo') {
            $legacyCatalogo = true;
        }
    }
} else {
    $confirm = (string) ($_GET['confirm'] ?? $_POST['confirm'] ?? '');
    $soloDemo = !empty($_GET['solo_demo']) || !empty($_POST['solo_demo']);
    $legacyCatalogo = !empty($_GET['legacy_catalogo']) || !empty($_POST['legacy_catalogo']);
}

if ($confirm !== PURGE_CONFIRM_PHRASE) {
    $msg = 'Debe confirmar con --confirm=' . PURGE_CONFIRM_PHRASE;
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $msg . "\n");
        exit(1);
    }
    throw new RuntimeException($msg);
}

purge_log('=== Purga de datos HAY ===');
$res = purge_datos_operativos($pdo, [
    'solo_demo' => $soloDemo,
    'legacy_catalogo' => $legacyCatalogo,
], static function (string $m): void {
    purge_log($m);
});

if (!$res['ok']) {
    purge_log('ERROR: ' . $res['message']);
    if (!empty($res['errores'])) {
        purge_log('Errores:');
        foreach ($res['errores'] as $err) {
            purge_log('  - ' . $err);
        }
    }
    if (!empty($res['restantes'])) {
        purge_log('Tablas con datos restantes:');
        foreach ($res['restantes'] as $t => $n) {
            purge_log("  - {$t}: {$n}");
        }
    }
    if (PHP_SAPI === 'cli') {
        exit(1);
    }
    throw new RuntimeException($res['message']);
}

purge_log($res['message']);
purge_log('Total filas afectadas (aprox.): ' . $res['filas']);
if (!empty($res['restantes'])) {
    purge_log('ADVERTENCIA — tablas que aún tienen filas:');
    foreach ($res['restantes'] as $t => $n) {
        purge_log("  - {$t}: {$n}");
    }
}
