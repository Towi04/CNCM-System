<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !reporte_academico_puede_ver()) {
    http_response_code(403);
    echo 'No autorizado';
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$format = strtolower(trim($_GET['format'] ?? 'csv'));
$rows = reporte_academico_resumen_grupos($pdo, $idPlantel);
$plantel = plantel_find($pdo, $idPlantel);
$titulo = trim((string) ($plantel['nombre'] ?? $plantel['clave'] ?? ''));

if ($format === 'csv' || $format === 'excel') {
    reporte_academico_enviar_csv($rows, 'reporte_academico_' . date('Y-m-d') . '.csv');
    exit;
}

if ($format === 'pdf') {
    $res = reporte_academico_generar_pdf($pdo, $idPlantel, $rows, $titulo);
    header('Content-Type: ' . $res['mime']);
    header('Content-Disposition: inline; filename="' . $res['filename'] . '"');
    header('Cache-Control: no-store');
    echo $res['contenido'];
    exit;
}

http_response_code(400);
echo 'Formato no válido. Use format=csv o format=pdf';
