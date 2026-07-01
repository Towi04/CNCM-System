<?php

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!fase_puede_editar()) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

$claveEsp = trim($_POST['clave_especialidad'] ?? 'ING');
if (!in_array($claveEsp, ['ING', 'ING-EXT'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Solo ING o ING-EXT'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_FILES['temario_xlsx']['tmp_name'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sube un archivo .xlsx'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tmp = $_FILES['temario_xlsx']['tmp_name'];
$name = $_FILES['temario_xlsx']['name'] ?? 'temario.xlsx';
if (!preg_match('/\.xlsx$/i', $name)) {
    echo json_encode(['status' => 'error', 'message' => 'El archivo debe ser .xlsx'], JSON_UNESCAPED_UNICODE);
    exit;
}

$destDir = dirname(__DIR__) . '/uploads/temarios';
if (!is_dir($destDir)) {
    mkdir($destDir, 0755, true);
}
$dest = $destDir . '/temario_' . date('Ymd_His') . '.xlsx';
if (!move_uploaded_file($tmp, $dest)) {
    copy($tmp, $dest);
}

$result = fase_importar_temario_xlsx($pdo, $dest, $claveEsp);
fase_sync_ingles_nomenclatura($pdo);

echo json_encode([
    'status' => $result['ok'] ? 'ok' : 'error',
    'message' => $result['message'],
    'actualizadas' => $result['actualizadas'] ?? 0,
    'seccion' => 'esp_fases',
], JSON_UNESCAPED_UNICODE);
