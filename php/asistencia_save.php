<?php
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard.php?seccion=asistencia');
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
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        hay_json_response(['status' => 'error', 'message' => 'Datos inválidos']);
    }
    header('Location: ../dashboard.php?seccion=asistencia&error=1');
    exit;
}

$res = asistencia_guardar_recepcion($pdo, $gid, $fecha, $ids, (int) $_SESSION['user_id']);

if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'fetch') {
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'seccion' => 'asistencia',
        'params' => 'grupo=' . $gid . '&fecha=' . urlencode($fecha),
    ]);
    exit;
}

header('Location: ../dashboard.php?seccion=asistencia&ok=1');
exit;
