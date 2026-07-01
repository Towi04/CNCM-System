<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    hay_json_response(['status' => 'error', 'message' => 'Sesión no válida']);
    exit;
}

$idUsuario = (int) $_SESSION['user_id'];
$codigo = $_POST['codigo_huella'] ?? '';

// Solo el propio usuario o quien puede editar usuarios
$idEditar = (int) ($_POST['id_usuario'] ?? $idUsuario);
if ($idEditar !== $idUsuario && !huella_puede_editar_usuario()) {
    http_response_code(403);
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$res = huella_asignar_usuario($pdo, $idEditar, $codigo, plantel_id_activo());
hay_json_response([
    'status' => $res['ok'] ? 'ok' : 'error',
    'message' => $res['message'],
    'codigo_huella' => $res['codigo_huella'] ?? '',
]);
