<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !reporte_presentados_puede_ver()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$filtros = [];
if (!empty($_GET['id_usuario_asesor'])) {
    $filtros['id_usuario_asesor'] = (int) $_GET['id_usuario_asesor'];
}
if (!empty($_GET['desde'])) {
    $filtros['desde'] = $_GET['desde'];
}
if (!empty($_GET['hasta'])) {
    $filtros['hasta'] = $_GET['hasta'];
}

try {
    $data = reporte_presentados_listar($pdo, $idPlantel, $filtros);
    hay_json_response(['status' => 'ok', 'filas' => $data['filas'], 'resumen' => $data['resumen']]);
} catch (Throwable $e) {
    error_log('reporte_presentados_api: ' . $e->getMessage());
    hay_json_response(['status' => 'error', 'message' => 'Error al cargar presentados.'], 500);
}
