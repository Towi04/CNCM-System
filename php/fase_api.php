<?php
require __DIR__ . '/../config.php';

if (!fase_puede_editar()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/fase_csv_helper.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'csv_download' || $action === 'csv_plantilla') {
    $idEsp = (int) ($_GET['id_especialidad'] ?? $_POST['id_especialidad'] ?? 0);
    fase_csv_enviar_descarga($pdo, $idEsp > 0 ? $idEsp : null, $action === 'csv_plantilla');
}

if ($action === 'csv_upload') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        hay_json_response(['status' => 'error', 'message' => 'Método inválido']);
        exit;
    }
    if (empty($_FILES['csv']['tmp_name']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
        hay_json_response(['status' => 'error', 'message' => 'Seleccione un archivo CSV']);
        exit;
    }
    $idEsp = (int) ($_POST['id_especialidad'] ?? 0);
    $res = fase_csv_importar($pdo, $_FILES['csv']['tmp_name'], $idEsp > 0 ? $idEsp : null);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'actualizadas' => $res['actualizadas'] ?? 0,
        'creadas' => $res['creadas'] ?? 0,
        'errores' => $res['errores'] ?? [],
        'seccion' => 'esp_fases',
    ]);
    exit;
}

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
