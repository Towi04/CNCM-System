<?php
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!marketing_puede_administrar()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'listar') {
    hay_json_response(['status' => 'ok', 'banners' => marketing_banners_admin_listar($pdo)]);
    exit;
}

if ($action === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = marketing_banner_guardar($pdo, $_POST);
    hay_json_response($res['ok']
        ? ['status' => 'ok', 'message' => 'Guardado', 'id_banner' => $res['id_banner'] ?? 0]
        : ['status' => 'error', 'message' => $res['message'] ?? 'Error']);
    exit;
}

if ($action === 'eliminar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id_banner'] ?? 0);
    $res = marketing_banner_eliminar($pdo, $id);
    hay_json_response($res['ok']
        ? ['status' => 'ok', 'message' => 'Eliminado']
        : ['status' => 'error', 'message' => $res['message'] ?? 'Error']);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
