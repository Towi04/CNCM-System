<?php
require __DIR__ . '/../config.php';

if (empty($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'Sesión expirada'], 401);
    exit;
}

if (!profesor_eval_puede_gestionar()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado'], 403);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$idPlantel = plantel_scope_id($pdo);
$idEvaluador = (int) $_SESSION['user_id'];

if ($action === 'metricas') {
    $idUsuario = (int) ($_GET['id_usuario'] ?? 0);
    $anio = (int) ($_GET['anio'] ?? (int) date('Y'));
    $mes = (int) ($_GET['mes'] ?? (int) date('n'));
    if ($idUsuario <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Profesor inválido']);
        exit;
    }
    $calc = profesor_eval_calcular_metricas_auto($pdo, $idUsuario, $idPlantel, $anio, $mes);
    hay_json_response(['status' => 'ok', 'data' => $calc]);
    exit;
}

if ($action === 'guardar') {
    $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
    $anio = (int) ($_POST['anio'] ?? (int) date('Y'));
    $mes = (int) ($_POST['mes'] ?? (int) date('n'));
    if ($idUsuario <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Profesor inválido']);
        exit;
    }

    $puntosAuto = [];
    foreach (profesor_eval_criterios_auto() as $c) {
        $puntosAuto[$c['codigo']] = (int) ($_POST['auto_' . $c['codigo']] ?? 0);
    }
    $puntosManual = [];
    foreach (profesor_eval_criterios_manual() as $c) {
        $puntosManual[$c['codigo']] = (int) ($_POST['manual_' . $c['codigo']] ?? 0);
    }

    $metricasJson = (string) ($_POST['metricas_auto_json'] ?? '{}');
    $metricasAuto = json_decode($metricasJson, true);
    if (!is_array($metricasAuto)) {
        $metricasAuto = [];
    }

    $cerrar = !empty($_POST['cerrar']);
    $res = profesor_eval_guardar(
        $pdo,
        $idUsuario,
        $idPlantel,
        $anio,
        $mes,
        $puntosAuto,
        $puntosManual,
        $metricasAuto,
        (string) ($_POST['observaciones'] ?? ''),
        $cerrar,
        $idEvaluador
    );
    if (!$res['ok']) {
        hay_json_response(['status' => 'error', 'message' => $res['message']]);
        exit;
    }
    hay_json_response([
        'status' => 'ok',
        'message' => $cerrar ? 'Evaluación cerrada' : 'Borrador guardado',
        'totales' => $res['totales'],
        'seccion' => 'profesor_evaluacion',
        'query' => 'id_usuario=' . $idUsuario . '&anio=' . $anio . '&mes=' . $mes,
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
