<?php
require __DIR__ . '/../config.php';

if (!usuario_puede_gestionar_alumnos()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idUsuario = (int) ($_POST['id_usuario'] ?? 0);
if ($idUsuario <= 0) {
    hay_json_response(['status' => 'error', 'message' => 'Usuario inválido']);
    exit;
}

$res = usuario_vincular_como_alumno($pdo, $idUsuario);
hay_json_response([
    'status' => ($res['ok'] ?? false) ? 'ok' : 'error',
    'message' => $res['message'] ?? '',
    'id_alumno' => $res['id_alumno'] ?? null,
]);

