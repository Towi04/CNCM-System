<?php
require __DIR__ . '/../config.php';

if (empty($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'Sesión expirada'], 401);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'procesar_plantel') {
    if (!grupo_avance_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $res = grupo_avance_procesar_plantel($pdo);
    hay_json_response([
        'status' => 'ok',
        'message' => "Avance automático: {$res['avanzados']} grupo(s) de {$res['procesados']} revisados",
        'data' => $res,
        'seccion' => 'grupos',
    ]);
    exit;
}

if ($action === 'avanzar_grupo') {
    if (!grupo_avance_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $idGrupo = (int) ($_POST['id_grupo'] ?? 0);
    $res = grupo_avance_ejecutar($pdo, $idGrupo, false, (int) $_SESSION['user_id']);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'data' => $res,
        'seccion' => 'grupos',
    ]);
    exit;
}

if ($action === 'resolver_riesgo') {
    if (!grupo_avance_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $acepto = $_POST['alumno_acepto_cambio'] ?? null;
    $aceptoBool = $acepto === '' || $acepto === null ? null : ($acepto === '1');
    $res = grupo_avance_resolver_riesgo(
        $pdo,
        (int) ($_POST['id_alumno'] ?? 0),
        (int) ($_POST['id_grupo'] ?? 0),
        (string) ($_POST['nota'] ?? ''),
        $aceptoBool,
        (int) $_SESSION['user_id']
    );
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'seccion' => 'academico_riesgo',
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida'], 400);
