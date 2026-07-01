<?php
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !rbac_usuario_puede_gestionar_privilegios()) {
    hay_json_response(['status' => 'error', 'message' => 'Sin permiso']);
    exit;
}

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));
$idUsuario = (int) ($_GET['id_usuario'] ?? $_POST['id_usuario'] ?? 0);

if ($action === 'listar') {
    if ($idUsuario <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Usuario requerido']);
        exit;
    }
    hay_json_response([
        'status' => 'ok',
        'privilegios' => rbac_usuario_privilegios_listar($pdo, $idUsuario),
        'catalogo' => rbac_privilegios_catalogo(),
        'restringidos' => rbac_privilegios_restringidos_asignacion(),
        'es_supervisor' => rbac_rol_real() === 'supervisor',
    ]);
    exit;
}

if ($action === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $items = $_POST['items'] ?? '[]';
    if (is_string($items)) {
        $items = json_decode($items, true) ?: [];
    }
    $res = rbac_usuario_privilegios_guardar($pdo, $idUsuario, is_array($items) ? $items : []);
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
