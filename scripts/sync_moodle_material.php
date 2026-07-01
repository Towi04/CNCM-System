<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/moodle_material_helper.php';

$idEsp = isset($argv[1]) ? (int) $argv[1] : null;
if ($idEsp !== null && $idEsp <= 0) {
    $idEsp = null;
}

$res = moodle_sync_academico_material($pdo, $idEsp);
echo ($res['message'] ?? 'Listo') . "\n";
if (!empty($res['insertados'])) {
    echo "Insertados: {$res['insertados']}\n";
}
if (!empty($res['actualizados'])) {
    echo "Actualizados: {$res['actualizados']}\n";
}
if (!empty($res['errores'])) {
    foreach ($res['errores'] as $e) {
        echo "  - $e\n";
    }
}
exit($res['ok'] ? 0 : 1);
