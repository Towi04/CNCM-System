<?php
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$res = pago_verificar_autorizador(
    $pdo,
    trim($_POST['usuario_autoriza'] ?? ''),
    (string) ($_POST['password_autoriza'] ?? '')
);

hay_json_response([
    'status' => $res['ok'] ? 'ok' : 'error',
    'message' => $res['message'] ?? '',
    'id_autoriza' => $res['id_usuario'] ?? null,
    'nombre_autoriza' => $res['nombre'] ?? null,
]);
