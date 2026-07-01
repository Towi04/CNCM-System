<?php

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !rbac_puede_administrar_roles()) {
    hay_json_response(['status' => 'error', 'message' => 'Sin permiso']);
    exit;
}

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));

if ($action === 'listar') {
    $roles = rbac_roles_listar($pdo, false);
    foreach ($roles as &$r) {
        $r['num_privilegios'] = count(rbac_rol_privilegios($pdo, (int) $r['id_rol']));
        if ((int) ($r['acceso_total'] ?? 0)) {
            $r['num_privilegios'] = count(rbac_privilegios_catalogo());
        }
    }
    unset($r);
    hay_json_response([
        'status' => 'ok',
        'roles' => $roles,
        'catalogo' => rbac_privilegios_catalogo(),
        'alcance_opciones' => rbac_alcance_planteles_opciones(),
        'planteles' => plantel_list($pdo, true),
    ]);
    exit;
}

if ($action === 'detalle') {
    $id = (int) ($_GET['id_rol'] ?? 0);
    $rol = rbac_rol_por_id($pdo, $id);
    if (!$rol) {
        hay_json_response(['status' => 'error', 'message' => 'Rol no encontrado']);
        exit;
    }
    hay_json_response([
        'status' => 'ok',
        'rol' => $rol,
        'privilegios' => rbac_rol_privilegios($pdo, $id),
        'planteles_ids' => rbac_rol_planteles_ids($pdo, $id),
        'catalogo' => rbac_privilegios_catalogo(),
        'alcance_opciones' => rbac_alcance_planteles_opciones(),
        'planteles' => plantel_list($pdo, true),
    ]);
    exit;
}

if ($action === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $privs = $_POST['privilegios'] ?? '[]';
    if (is_string($privs)) {
        $privs = json_decode($privs, true) ?: [];
    }
    $planteles = $_POST['planteles'] ?? '[]';
    if (is_string($planteles)) {
        $planteles = json_decode($planteles, true) ?: [];
    }
    $res = rbac_rol_guardar($pdo, [
        'id_rol' => (int) ($_POST['id_rol'] ?? 0),
        'clave' => $_POST['clave'] ?? '',
        'nombre' => $_POST['nombre'] ?? '',
        'descripcion' => $_POST['descripcion'] ?? '',
        'acceso_total' => $_POST['acceso_total'] ?? 0,
        'activo' => $_POST['activo'] ?? 1,
        'alcance_planteles' => $_POST['alcance_planteles'] ?? 'solo_usuario',
        'planteles' => $planteles,
        'privilegios' => $privs,
    ]);
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

if ($action === 'roles_formulario') {
    hay_json_response(['status' => 'ok', 'roles' => rbac_roles_para_formulario($pdo)]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
