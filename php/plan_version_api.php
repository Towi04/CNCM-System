<?php
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

if (!fase_puede_editar()) {
    hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'listar') {
    $idEsp = (int) ($_GET['id_especialidad'] ?? 0);
    if ($idEsp <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Especialidad requerida']);
        exit;
    }
    hay_json_response([
        'status' => 'ok',
        'versiones' => plan_version_listar($pdo, $idEsp),
        'activo' => plan_version_activo_nuevos($pdo, $idEsp),
    ]);
    exit;
}

if ($action === 'publicar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $idEsp = (int) ($_POST['id_especialidad'] ?? 0);
    $label = trim((string) ($_POST['version_label'] ?? ''));
    if ($idEsp <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Especialidad requerida']);
        exit;
    }
    $res = plan_version_publicar($pdo, $idEsp, $label);
    hay_json_response(array_merge(['status' => 'ok'], $res));
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
