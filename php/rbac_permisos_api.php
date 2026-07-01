<?php
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !rbac_puede_centro_permisos()) {
    hay_json_response(['status' => 'error', 'message' => 'Sin permiso']);
    exit;
}

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));
$idPlantel = plantel_scope_id($pdo);

if ($action === 'personal_listar') {
    $q = trim((string) ($_GET['q'] ?? ''));
    $todos = !empty($_GET['todos_planteles']) && rbac_tiene_acceso_total();
    hay_json_response([
        'status' => 'ok',
        'usuarios' => rbac_personal_listar($pdo, $todos ? null : $idPlantel, $q),
        'roles' => rbac_roles_para_formulario($pdo),
    ]);
    exit;
}

if ($action === 'personal_detalle') {
    $idUsuario = (int) ($_GET['id_usuario'] ?? 0);
    $det = rbac_personal_detalle($pdo, $idUsuario);
    if (!$det) {
        hay_json_response(['status' => 'error', 'message' => 'Usuario no encontrado']);
        exit;
    }
    hay_json_response([
        'status' => 'ok',
        'detalle' => $det,
        'catalogo' => rbac_privilegios_catalogo(),
        'restringidos' => rbac_privilegios_restringidos_asignacion(),
        'roles' => rbac_roles_para_formulario($pdo),
        'es_supervisor' => rbac_rol_real() === 'supervisor',
    ]);
    exit;
}

if ($action === 'personal_rol' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
    $idRol = (int) ($_POST['id_rol'] ?? 0);
    $res = rbac_usuario_cambiar_rol($pdo, $idUsuario, $idRol);
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

if ($action === 'personal_guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
    $items = $_POST['items'] ?? '[]';
    if (is_string($items)) {
        $items = json_decode($items, true) ?: [];
    }
    $res = rbac_usuario_privilegios_guardar($pdo, $idUsuario, is_array($items) ? $items : []);
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

if ($action === 'personal_limpiar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
    if ($idUsuario <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Usuario inválido']);
        exit;
    }
    $pdo->prepare('DELETE FROM usuario_privilegios WHERE id_usuario = ?')->execute([$idUsuario]);
    rbac_usuario_sync_permisos_personalizados($pdo, $idUsuario);
    hay_json_response(['status' => 'ok', 'message' => 'Permisos personalizados eliminados; solo aplica el rol base']);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
