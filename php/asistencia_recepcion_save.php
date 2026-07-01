<?php
require __DIR__ . '/../config.php';

if (!asistencia_puede_tomar()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$gid = (int) ($_POST['id_grupo'] ?? 0);
$fecha = trim($_POST['fecha'] ?? '');
$presente = $_POST['presente'] ?? [];
$ids = [];
if (is_array($presente)) {
    foreach ($presente as $k => $v) {
        $ids[] = (int) $k;
    }
}

if ($gid <= 0 || $fecha === '') {
    hay_json_response(['status' => 'error', 'message' => 'Grupo y fecha son obligatorios']);
    exit;
}

$res = asistencia_guardar_recepcion(
    $pdo,
    $gid,
    $fecha,
    $ids,
    (int) ($_SESSION['user_id'] ?? 0)
);

hay_json_response([
    'status' => $res['ok'] ? 'ok' : 'error',
    'message' => $res['message'],
    'seccion' => 'asistencia',
    'params' => 'grupo=' . $gid . '&fecha=' . urlencode($fecha),
]);
