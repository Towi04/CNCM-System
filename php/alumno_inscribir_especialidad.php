<?php
require __DIR__ . '/../config.php';

if (!alumno_puede_ver()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$res = alumno_inscribir_especialidad(
    $pdo,
    (int) ($_POST['id_alumno'] ?? 0),
    (int) ($_POST['id_especialidad'] ?? 0),
    (int) ($_POST['id_grupo'] ?? 0) ?: null,
    trim($_POST['forma_pago'] ?? 'mensual') ?: 'mensual'
);

hay_json_response([
    'status' => $res['ok'] ? 'ok' : 'error',
    'message' => $res['message'],
    'seccion' => 'alumno_detalle',
    'params' => 'id=' . (int) ($_POST['id_alumno'] ?? 0),
    'combo' => $res['combo'] ?? null,
]);
