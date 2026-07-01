<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'No autenticado'], 401);
    exit;
}

$action = trim((string) ($_POST['action'] ?? $_GET['action'] ?? ''));

if ($action === 'desbloquear') {
    if (!login_security_puede_desbloquear()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
    $res = login_security_desbloquear($pdo, $idUsuario, (int) $_SESSION['user_id']);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'] ?? '',
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
