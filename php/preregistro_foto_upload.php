<?php
require __DIR__ . '/../config.php';

if (empty($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'Sesión expirada. Vuelva a iniciar sesión.'], 401);
    exit;
}

if (!preregistro_puede_acceder()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado'], 403);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hay_json_response(['status' => 'error', 'message' => 'Método inválido']);
    exit;
}

if (empty($_FILES['foto']['tmp_name'])) {
    hay_json_response(['status' => 'error', 'message' => 'No se recibió la imagen']);
    exit;
}

$up = preregistro_guardar_archivo($_FILES['foto'], PREREG_FOTO_DIR, 'foto');
if (!$up['ok'] || empty($up['path'])) {
    hay_json_response(['status' => 'error', 'message' => $up['message'] ?? 'No se pudo guardar la foto']);
    exit;
}

preregistro_foto_sesion_asignar($up['path']);

hay_json_response([
    'status' => 'ok',
    'message' => 'Foto lista para guardar',
]);
