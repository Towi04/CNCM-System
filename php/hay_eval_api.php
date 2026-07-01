<?php

declare(strict_types=1);
require __DIR__ . '/../config.php';

if (empty($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'Sesión expirada'], 401);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$idPlantel = plantel_id_activo();
$idSesion = (int) $_SESSION['user_id'];

if ($action === 'mi_resumen') {
    $idAreaReq = (int) ($_GET['id_area'] ?? 0) ?: null;
    $idArea = hay_eval_area_usuario($pdo, $idSesion, null, $idAreaReq);
    $periodos = hay_eval_listar_periodos_usuario($pdo, $idSesion, $idArea);
    $matriz = $idArea ? hay_eval_matriz_usuario($pdo, $idSesion, null, $idArea) : ['capacitaciones' => []];
    $areas = function_exists('hay_eval_areas_usuario') ? hay_eval_areas_usuario($pdo, $idSesion) : [];
    hay_json_response([
        'status' => 'ok',
        'id_area' => $idArea,
        'areas' => $areas,
        'periodos' => $periodos,
        'matriz' => $matriz,
        'sueldo_sugerido' => $idArea ? hay_eval_sueldo_sugerido_usuario($pdo, $idSesion, $idArea) : null,
    ]);
    exit;
}

if ($action === 'guardar_areas_usuario') {
    if (!hay_eval_puede_gestionar() && !rbac_cap('admin_usuarios')) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado'], 403);
        exit;
    }
    $idTarget = (int) ($_POST['id_usuario'] ?? 0);
    $areas = $_POST['areas'] ?? [];
    if (is_string($areas)) {
        $areas = array_filter(array_map('intval', explode(',', $areas)));
    }
    $principal = (int) ($_POST['id_area_principal'] ?? 0) ?: null;
    $res = hay_eval_asignar_areas_usuario($pdo, $idTarget, (array) $areas, $principal);
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message']]);
    exit;
}

if ($action === 'areas_usuario') {
    $idTarget = (int) ($_GET['id_usuario'] ?? $idSesion);
    hay_json_response([
        'status' => 'ok',
        'areas' => hay_eval_areas_usuario($pdo, $idTarget),
        'catalogo' => hay_eval_listar_areas($pdo),
    ]);
    exit;
}

if ($action === 'colaboradores') {
    if (!hay_eval_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado'], 403);
        exit;
    }
    $idArea = (int) ($_GET['id_area'] ?? 0);
    $anio = (int) ($_GET['anio'] ?? date('Y'));
    $mes = (int) ($_GET['mes'] ?? date('n'));
    if ($idArea <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Seleccione área']);
        exit;
    }
    $cols = hay_eval_listar_colaboradores_area($pdo, $idArea, $idPlantel);
    foreach ($cols as &$c) {
        $ev = hay_eval_obtener_o_crear_periodo($pdo, (int) $c['id_usuario'], $idPlantel, $idArea, $anio, $mes);
        $c['id_eval'] = (int) ($ev['id_eval'] ?? 0);
        $c['estado_eval'] = $ev['estado'] ?? '';
        $c['puntos_total'] = (int) ($ev['puntos_total'] ?? 0);
    }
    unset($c);
    hay_json_response(['status' => 'ok', 'colaboradores' => $cols]);
    exit;
}

if ($action === 'obtener_eval') {
    $idEval = (int) ($_GET['id_eval'] ?? 0);
    $eval = hay_eval_obtener_periodo($pdo, $idEval);
    if (!$eval) {
        hay_json_response(['status' => 'error', 'message' => 'No encontrado']);
        exit;
    }
    $puedeVer = hay_eval_puede_gestionar()
        || (int) $eval['id_usuario'] === $idSesion;
    if (!$puedeVer) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado'], 403);
        exit;
    }
    if (!hay_eval_puede_gestionar() && ($eval['estado'] ?? '') !== 'cerrado') {
        hay_json_response(['status' => 'error', 'message' => 'Evaluación no disponible'], 403);
        exit;
    }
    $rubrica = hay_eval_rubrica_completa($pdo, (int) $eval['id_area']);
    $respuestas = hay_eval_cargar_respuestas($pdo, $idEval);
    hay_json_response([
        'status' => 'ok',
        'eval' => $eval,
        'rubrica' => $rubrica,
        'respuestas' => $respuestas,
    ]);
    exit;
}

if ($action === 'guardar_respuestas' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hay_eval_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado'], 403);
        exit;
    }
    $idEval = (int) ($_POST['id_eval'] ?? 0);
    $resp = $_POST['respuestas'] ?? [];
    if (is_string($resp)) {
        $decoded = json_decode($resp, true);
        $resp = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($resp)) {
        $resp = [];
    }
    $res = hay_eval_guardar_respuestas($pdo, $idEval, $resp);
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

if ($action === 'cerrar_periodo' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hay_eval_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado'], 403);
        exit;
    }
    $res = hay_eval_cerrar_periodo(
        $pdo,
        (int) ($_POST['id_eval'] ?? 0),
        $idSesion,
        trim((string) ($_POST['observaciones'] ?? '')) ?: null
    );
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

if ($action === 'sync_moodle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hay_eval_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado'], 403);
        exit;
    }
    $eval = hay_eval_obtener_periodo($pdo, (int) ($_POST['id_eval'] ?? 0));
    if (!$eval) {
        hay_json_response(['status' => 'error', 'message' => 'No encontrada']);
        exit;
    }
    $res = function_exists('hay_eval_sync_moodle_respuestas')
        ? hay_eval_sync_moodle_respuestas($pdo, (int) $eval['id_eval'], (int) $eval['id_usuario'])
        : ['ok' => false, 'message' => 'Moodle no disponible'];
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

if ($action === 'sincronizar_auto' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hay_eval_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado'], 403);
        exit;
    }
    $res = hay_eval_sincronizar_metricas_auto($pdo, (int) ($_POST['id_eval'] ?? 0));
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

if ($action === 'marcar_capacitacion' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hay_eval_puede_matriz_marcar()) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado'], 403);
        exit;
    }
    $res = hay_eval_marcar_capacitacion(
        $pdo,
        (int) ($_POST['id_usuario'] ?? 0),
        (int) ($_POST['id_capacitacion'] ?? 0),
        (string) ($_POST['periodo'] ?? date('Y-m')),
        !empty($_POST['completada']),
        $idSesion,
        trim((string) ($_POST['notas'] ?? '')) ?: null
    );
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

if ($action === 'matriz_usuario') {
    $idUser = (int) ($_GET['id_usuario'] ?? $idSesion);
    if ($idUser !== $idSesion && !hay_eval_puede_matriz_marcar()) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado'], 403);
        exit;
    }
    $res = hay_eval_matriz_usuario($pdo, $idUser, $_GET['periodo'] ?? null);
    hay_json_response(array_merge(['status' => ($res['ok'] ?? true) ? 'ok' : 'error'], $res));
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
