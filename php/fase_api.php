<?php
require __DIR__ . '/../config.php';

if (!fase_puede_editar()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'save') {
    $res = fase_guardar($pdo, $_POST);
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message']]);
    exit;
}

if ($action === 'delete') {
    $res = fase_eliminar($pdo, (int) ($_POST['id_fase'] ?? 0));
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message']]);
    exit;
}

if ($action === 'get') {
    $id = (int) ($_GET['id_fase'] ?? 0);
    $row = $id > 0 ? fase_obtener($pdo, $id) : null;
    hay_json_response([
        'status' => $row ? 'ok' : 'error',
        'fase' => $row,
        'message' => $row ? '' : 'Fase no encontrada',
    ]);
    exit;
}

if ($action === 'temario_semanas') {
    $id = (int) ($_GET['id_fase'] ?? 0);
    hay_json_response([
        'status' => 'ok',
        'semanas' => $id > 0 ? fase_temario_semanas($pdo, $id) : [],
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
