<?php
require __DIR__ . '/../config.php';

if (!usuario_puede_gestionar_alumnos()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$res = usuario_reset_password_alumno($pdo, (int) ($_POST['id_alumno'] ?? 0));
hay_json_response([
    'status' => $res['ok'] ? 'ok' : 'error',
    'message' => $res['message'],
    'seccion' => 'alumno_detalle',
    'params' => 'id=' . (int) ($_POST['id_alumno'] ?? 0),
]);
