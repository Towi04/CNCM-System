<?php
require __DIR__ . '/../config.php';

if (!alumno_puede_ver()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$res = alumno_inscribir_kids(
    $pdo,
    (int) ($_POST['id_alumno'] ?? 0),
    (int) ($_POST['id_grupo_ingles'] ?? 0),
    (int) ($_POST['id_grupo_computacion'] ?? 0),
    trim($_POST['forma_pago'] ?? 'mensual') ?: 'mensual'
);

hay_json_response([
    'status' => $res['ok'] ? 'ok' : 'error',
    'message' => $res['message'],
    'seccion' => 'alumno_detalle',
    'params' => 'id=' . (int) ($_POST['id_alumno'] ?? 0),
]);
