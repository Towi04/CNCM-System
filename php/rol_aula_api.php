<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$accion = trim($_POST['accion'] ?? $_GET['accion'] ?? '');

$lectura = in_array($accion, ['listar', 'obtener', 'validar', 'publicaciones'], true);
$escritura = in_array($accion, ['generar', 'guardar_asignaciones', 'publicar'], true);

if ($lectura && !rol_aula_puede_ver()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}
if ($escritura && !rol_aula_puede_gestionar()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}
if (!$lectura && !$escritura) {
    hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
    exit;
}

if ($accion === 'publicaciones') {
    hay_json_response([
        'status' => 'ok',
        'publicaciones' => rol_aula_listar_publicaciones($pdo, $idPlantel),
    ]);
    exit;
}

if ($accion === 'obtener') {
    $anio = (int) ($_GET['anio'] ?? $_POST['anio'] ?? date('Y'));
    $mes = (int) ($_GET['mes'] ?? $_POST['mes'] ?? date('n'));
    $idPub = (int) ($_GET['id_publicacion'] ?? $_POST['id_publicacion'] ?? 0);

    if ($idPub > 0) {
        $pub = rol_aula_obtener($pdo, $idPub, $idPlantel);
    } else {
        $row = rol_aula_obtener_periodo($pdo, $idPlantel, $anio, $mes);
        $pub = $row ? rol_aula_obtener($pdo, (int) $row['id_publicacion'], $idPlantel) : null;
    }

    hay_json_response([
        'status' => 'ok',
        'publicacion' => $pub,
        'aulas' => aula_listar_plantel($pdo, $idPlantel, true),
    ]);
    exit;
}

if ($accion === 'generar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $anio = (int) ($_POST['anio'] ?? date('Y'));
    $mes = (int) ($_POST['mes'] ?? date('n'));
    $res = rol_aula_generar($pdo, $idPlantel, $anio, $mes, (int) $_SESSION['user_id']);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'id_publicacion' => $res['id_publicacion'] ?? null,
    ]);
    exit;
}

if ($accion === 'guardar_asignaciones' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $idPub = (int) ($_POST['id_publicacion'] ?? 0);
    $cambios = json_decode($_POST['cambios'] ?? '[]', true);
    if (!is_array($cambios)) {
        $cambios = [];
    }
    $res = rol_aula_guardar_asignaciones($pdo, $idPlantel, $idPub, $cambios);
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message']]);
    exit;
}

if ($accion === 'validar') {
    $idPub = (int) ($_GET['id_publicacion'] ?? $_POST['id_publicacion'] ?? 0);
    $res = rol_aula_validar($pdo, $idPlantel, $idPub);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'conflictos' => $res['conflictos'] ?? [],
    ]);
    exit;
}

if ($accion === 'publicar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $idPub = (int) ($_POST['id_publicacion'] ?? 0);
    $res = rol_aula_publicar($pdo, $idPlantel, $idPub, (int) $_SESSION['user_id']);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'notificados' => $res['notificados'] ?? 0,
        'conflictos' => $res['conflictos'] ?? [],
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
