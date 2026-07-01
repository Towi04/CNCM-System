<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'Sesión expirada'], 401);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$action = trim($_GET['action'] ?? $_POST['action'] ?? '');
$idUsuario = (int) $_SESSION['user_id'];

if ($action === 'mis_planeaciones') {
    if (!planeacion_puede_crear()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $filtros = ['id_profesor' => $idUsuario];
    $items = planeacion_listar($pdo, $idPlantel, $filtros, 30);
    foreach ($items as &$it) {
        $it['puede_reenviar'] = planeacion_puede_reenviar($pdo, (int) $it['id_planeacion'], $idPlantel);
        $it['num_observaciones'] = count(planeacion_observaciones_listar($pdo, (int) $it['id_planeacion']));
    }
    unset($it);
    hay_json_response([
        'status' => 'ok',
        'items' => $items,
        'estados' => planeacion_estados_etiquetas(),
    ]);
    exit;
}

if ($action === 'detalle') {
    $id = (int) ($_GET['id_planeacion'] ?? $_POST['id_planeacion'] ?? 0);
    if (!planeacion_puede_ver($pdo, $id, $idPlantel)) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $plan = planeacion_obtener($pdo, $id, $idPlantel);
    if (!$plan) {
        hay_json_response(['status' => 'error', 'message' => 'No encontrada']);
        exit;
    }
    hay_json_response([
        'status' => 'ok',
        'planeacion' => $plan,
        'observaciones' => planeacion_observaciones_listar($pdo, $id),
        'puede_reenviar' => planeacion_puede_reenviar($pdo, $id, $idPlantel),
        'puede_comentar' => planeacion_puede_comentar($pdo, $id, $idPlantel),
        'puede_revisar' => planeacion_puede_revisar(),
        'fases' => planeacion_fases_grupo($pdo, (int) $plan['id_grupo']),
    ]);
    exit;
}

if ($action === 'comentar') {
    $id = (int) ($_POST['id_planeacion'] ?? 0);
    $nota = trim((string) ($_POST['nota'] ?? ''));
    $marcarObs = !empty($_POST['marcar_observada']);
    $res = planeacion_agregar_comentario($pdo, $id, $nota, $idUsuario, $idPlantel, $marcarObs);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
    ]);
    exit;
}

if ($action === 'reenviar') {
    $id = (int) ($_POST['id_planeacion'] ?? 0);
    $res = planeacion_reenviar($pdo, $id, $_POST, $idUsuario, $idPlantel);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
    ]);
    exit;
}

if (!planeacion_puede_revisar()) {
    hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
    exit;
}

if ($action === 'listar') {
    $estado = trim($_GET['estado'] ?? 'enviada');
    $filtros = [];
    if ($estado !== '' && $estado !== 'todas') {
        $filtros['estado'] = $estado;
    }
    $idGrupo = (int) ($_GET['id_grupo'] ?? 0);
    if ($idGrupo > 0) {
        $filtros['id_grupo'] = $idGrupo;
    }
    $idProfesor = (int) ($_GET['id_profesor'] ?? 0);
    if ($idProfesor > 0) {
        $filtros['id_profesor'] = $idProfesor;
    }
    hay_json_response([
        'status' => 'ok',
        'items' => planeacion_listar($pdo, $idPlantel, $filtros, 60),
        'pendientes' => planeacion_contar_pendientes($pdo, $idPlantel),
        'estados' => planeacion_estados_etiquetas(),
    ]);
    exit;
}

if ($action === 'revisar') {
    $id = (int) ($_POST['id_planeacion'] ?? 0);
    $estado = trim((string) ($_POST['estado'] ?? ''));
    $nota = trim((string) ($_POST['nota'] ?? ''));
    $res = planeacion_resolver_revision($pdo, $id, $estado, $nota, $idUsuario, $idPlantel);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida'], 400);
