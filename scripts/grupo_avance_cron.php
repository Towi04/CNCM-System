<?php
/**
 * Ejecutar diario (cron) para avance automático de parciales por grupo.
 * Ejemplo: php scripts/grupo_avance_cron.php
 */
if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Solo CLI\n");
    exit(1);
}

require dirname(__DIR__) . '/config.php';

$idPlantel = (int) ($argv[1] ?? 0);
if ($idPlantel > 0) {
    $_SESSION['id_plantel_activo'] = $idPlantel;
}

$avance = grupo_avance_procesar_plantel($pdo, $idPlantel > 0 ? $idPlantel : null);
$grad = graduacion_generar_alertas_automaticas($pdo, $idPlantel > 0 ? $idPlantel : null);
echo json_encode([
    'avance_grupo' => $avance,
    'graduacion_alertas' => $grad,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
