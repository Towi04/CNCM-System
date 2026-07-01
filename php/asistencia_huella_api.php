<?php
/**
 * Lector de huella fijo (ZKTeco y similares) — sin sesión web.
 * POST: codigo_huella | opcional: fecha_hora, plantel_id, api_key
 * Registra alumnos y personal del plantel. No usar desde celular de profesores.
 */
declare(strict_types=1);

require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!asistencia_huella_api_key_valida()) {
    http_response_code(403);
    hay_json_response(['ok' => false, 'status' => 'error', 'message' => 'API key inválida']);
    exit;
}

$codigo = trim($_POST['codigo_huella'] ?? $_GET['codigo_huella'] ?? '');
$fechaHora = trim($_POST['fecha_hora'] ?? $_GET['fecha_hora'] ?? '') ?: date('Y-m-d H:i:s');
$idPlantel = (int) ($_POST['plantel_id'] ?? $_GET['plantel_id'] ?? 0);

if ($idPlantel <= 0 && !empty($_SESSION['plantel_id'])) {
    $idPlantel = (int) $_SESSION['plantel_id'];
}
if ($idPlantel <= 0) {
    $idPlantel = plantel_id_activo();
}

if ($codigo === '') {
    hay_json_response(['ok' => false, 'status' => 'error', 'message' => 'codigo_huella requerido']);
    exit;
}

$res = asistencia_procesar_codigo_huella($pdo, $codigo, $idPlantel, $fechaHora, 'lector_fijo', null);
hay_json_response([
    'ok' => $res['ok'] ?? false,
    'status' => ($res['ok'] ?? false) ? 'ok' : 'error',
    'message' => $res['message'] ?? '',
    'duplicado' => $res['duplicado'] ?? false,
    'tipo' => $res['tipo'] ?? null,
    'nombre' => $res['nombre'] ?? null,
    'id_alumno' => $res['id_alumno'] ?? null,
    'id_grupo' => $res['id_grupo'] ?? null,
    'id_usuario' => $res['id_usuario'] ?? null,
]);
