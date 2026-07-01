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
    $st = $pdo->prepare('SELECT rol, id_plantel FROM usuarios WHERE id_usuario = ? LIMIT 1');
    $st->execute([$idUsuario]);
    $u = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $rol = strtolower(trim((string) ($u['rol'] ?? '')));
    hay_json_response([
        'status' => 'ok',
        'planteles' => rbac_usuario_planteles_listar($pdo, $idUsuario),
        'catalogo' => plantel_list($pdo, true),
        'id_plantel_home' => (int) ($u['id_plantel'] ?? 0),
        'puede_asignar' => in_array($rol, plantel_roles_con_apoyo_temporal(), true),
        'roles_apoyo' => plantel_roles_con_apoyo_temporal(),
    ]);
    exit;
}

if ($action === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $items = $_POST['items'] ?? '[]';
    if (is_string($items)) {
        $items = json_decode($items, true) ?: [];
    }
    $res = rbac_usuario_planteles_guardar($pdo, $idUsuario, is_array($items) ? $items : []);
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
