<?php
require __DIR__ . '/../config.php';
require_once __DIR__ . '/especialidad_csv_helper.php';

if (!catalog_puede_administrar()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'download' || $action === 'plantilla') {
    especialidad_csv_enviar_descarga($pdo, $action === 'plantilla');
}

if ($action === 'upload') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        hay_json_response(['status' => 'error', 'message' => 'Método inválido']);
        exit;
    }
    if (empty($_FILES['csv']['tmp_name']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
        hay_json_response(['status' => 'error', 'message' => 'Seleccione un archivo CSV']);
        exit;
    }
    $ext = strtolower(pathinfo((string) ($_FILES['csv']['name'] ?? ''), PATHINFO_EXTENSION));
    if ($ext !== 'csv' && $ext !== 'txt') {
        hay_json_response(['status' => 'error', 'message' => 'El archivo debe ser .csv']);
        exit;
    }
    $res = especialidad_csv_importar($pdo, $_FILES['csv']['tmp_name']);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'actualizadas' => $res['actualizadas'] ?? 0,
        'errores' => $res['errores'] ?? [],
        'seccion' => 'admin_especialidades',
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
